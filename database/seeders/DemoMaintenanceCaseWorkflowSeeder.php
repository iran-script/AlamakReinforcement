<?php

namespace Database\Seeders;

use App\Http\Controllers\MaintenanceCaseController;
use App\Http\Controllers\TileController;
use App\Models\GroupUser;
use App\Models\MaintenanceCase;
use App\Models\OperationCategory;
use App\Models\Organization;
use App\Models\Riser;
use App\Models\User;
use App\Models\Zone;
use App\Services\AuditLogger;
use App\Services\ReportService;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Throwable;

/** Idempotent, local-only data for browser testing of the existing MaintenanceCase workflow. */
class DemoMaintenanceCaseWorkflowSeeder extends Seeder
{
    private const DATABASE = 'alamakreinforcement';
    private const CLUSTER_ID = '6882458043277858192';
    private const ZONE_NAME = 'TEST-ZONE-RISER-DEMO';
    private const ORG_CODE = 'DEMO-MAINT-WORKFLOW';
    private const BOSS_USERNAME = 'demo-maint-boss';
    private const SUPERVISORS = ['demo-maint-supervisor-01', 'demo-maint-supervisor-02'];
    private const GROUP_CODES = ['DEMO-MAINT-CREW-01', 'DEMO-MAINT-CREW-02'];

    /** @var list<string> */
    private array $tempUploadPaths = [];

    public function run(): void
    {
        $this->assertSafeTarget();
        $this->assertSchemaAndDemoAssets();
        $disk = Storage::disk('local');
        $filesBefore = $this->demoPhotoFiles($disk);
        $generatedPassword = null;

        try {
            $result = DB::transaction(function () use (&$generatedPassword): array {
                DB::selectOne("SELECT pg_advisory_xact_lock(hashtext('demo-maintenance-case-workflow'))");
                $this->assertSafeTarget();
                $superadminSnapshot = $this->superadminSnapshot();
                $admin = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'superadmin')->where('guard_name', 'web'))->orderBy('id')->first();
                if (! $admin) throw new RuntimeException('An existing Superadmin is required to audit the local demo setup.');
                $this->request($admin, 'POST', '/local-demo/maintenance-workflow', []);
                $targetOperationCosts = $this->testOperationCosts();

                $zone = DB::table('zone')->where('name', self::ZONE_NAME)->lockForUpdate()->first();
                $organization = Organization::query()->firstOrCreate(
                    ['code' => self::ORG_CODE],
                    ['name' => 'سازمان آزمایشی گردش کار تعمیرات', 'status' => 'active']
                );
                if ($organization->wasRecentlyCreated) {
                    app(AuditLogger::class)->record('demo.maintenance_organization_created', null, null, [
                        'organization_id' => (int) $organization->id, 'code' => $organization->code,
                    ], ['synthetic' => true]);
                }
                if ($zone->organization_id !== null && (int) $zone->organization_id !== (int) $organization->id) {
                    throw new RuntimeException('The named TEST Zone already belongs to a different organization; no data changed.');
                }
                if ($zone->organization_id === null) {
                    DB::table('zone')->where('id', $zone->id)->update(['organization_id' => $organization->id]);
                    app(AuditLogger::class)->record('demo.maintenance_zone_linked', Zone::query()->findOrFail($zone->id), ['organization_id' => null], [
                        'zone_id' => (int) $zone->id, 'organization_id' => (int) $organization->id,
                    ], ['synthetic' => true]);
                }

                $this->assertRisersInsideZone((int) $zone->id);
                $groups = [];
                foreach (self::GROUP_CODES as $index => $code) {
                    $group = GroupUser::query()->firstOrCreate(
                        ['name' => $code],
                        ['description' => 'Synthetic maintenance crew '.$code, 'organization_id' => $organization->id, 'zone_id' => $zone->id]
                    );
                    if ((int) $group->organization_id !== (int) $organization->id || (int) $group->zone_id !== (int) $zone->id) {
                        throw new RuntimeException("Demo group [{$code}] is assigned outside the approved synthetic scope.");
                    }
                    if ($group->wasRecentlyCreated) {
                        app(AuditLogger::class)->record('demo.maintenance_group_created', $group, null, [
                            'group_id' => (int) $group->id, 'name' => $group->name,
                            'organization_id' => (int) $group->organization_id, 'zone_id' => (int) $group->zone_id,
                        ], ['synthetic' => true]);
                    }
                    $groups[] = $group;
                }

                $supervisors = [];
                foreach (self::SUPERVISORS as $index => $username) {
                    $supervisor = User::query()->where('username', $username)->lockForUpdate()->firstOrFail();
                    if (! $supervisor->hasRole('supervisor') || (int) $supervisor->zone_id !== (int) $zone->id
                        || ! in_array($supervisor->organization_id, [null, $organization->id], true)
                        || ! in_array($supervisor->group_user_id, [null, $groups[$index]->id], true)) {
                        throw new RuntimeException("Supervisor [{$username}] is not an unambiguous demo supervisor in the TEST Zone.");
                    }
                    $before = ['organization_id' => $supervisor->organization_id, 'zone_id' => $supervisor->zone_id, 'group_user_id' => $supervisor->group_user_id];
                    $scopeChanged = (int) $supervisor->organization_id !== (int) $organization->id
                        || (int) $supervisor->zone_id !== (int) $zone->id || (int) $supervisor->group_user_id !== (int) $groups[$index]->id;
                    if ($scopeChanged) {
                        $supervisor->forceFill([
                            'organization_id' => $organization->id,
                            'zone_id' => $zone->id,
                            'group_user_id' => $groups[$index]->id,
                        ])->save();
                        app(AuditLogger::class)->record('demo.maintenance_supervisor_group_assigned', $supervisor, $before, [
                            'organization_id' => $supervisor->organization_id, 'zone_id' => $supervisor->zone_id, 'group_user_id' => $supervisor->group_user_id,
                        ], ['synthetic' => true]);
                    }
                    $supervisors[] = $supervisor->fresh();
                }

                [$boss, $generatedPassword] = $this->boss($organization, $zone, $groups[0]);
                $beforeManagedGroups = $boss->managedGroupIds();
                foreach ($groups as $group) {
                    $isAlreadyManaged = DB::table('boss_managed_groups')->where('boss_user_id', $boss->id)
                        ->where('group_user_id', $group->id)->exists();
                    if (! $isAlreadyManaged) $boss->managedGroups()->attach($group->id, ['assigned_by' => $this->superadminId()]);
                }
                if ($boss->managedGroupIds() !== [(int) $groups[0]->id, (int) $groups[1]->id]) {
                    $actual = $boss->managedGroupIds();
                    sort($actual);
                    $expected = [(int) $groups[0]->id, (int) $groups[1]->id];
                    sort($expected);
                    if ($actual !== $expected) throw new RuntimeException('Boss group assignments do not match the two synthetic groups.');
                }
                if ($beforeManagedGroups !== $boss->managedGroupIds()) {
                    app(AuditLogger::class)->record('demo.maintenance_boss_groups_assigned', $boss, ['group_ids' => $beforeManagedGroups], [
                        'group_ids' => $boss->managedGroupIds(),
                    ], ['synthetic' => true]);
                }

                $cases = $this->ensureCases($groups, $supervisors, $boss);
                if ($superadminSnapshot !== $this->superadminSnapshot()) {
                    throw new RuntimeException('Existing Superadmin changed; the transaction was rolled back.');
                }
                if (! $this->operationCostsUnchanged($targetOperationCosts)) {
                    throw new RuntimeException('An existing TEST operation total_cost changed; the transaction was rolled back.');
                }

                $result = [
                    'organization_id' => (int) $organization->id,
                    'zone_id' => (int) $zone->id,
                    'boss_id' => (int) $boss->id,
                    'boss_username' => $boss->username,
                    'group_ids' => array_map(fn (GroupUser $group): int => (int) $group->id, $groups),
                    'supervisor_ids' => array_map(fn (User $user): int => (int) $user->id, $supervisors),
                    'cases' => $cases,
                    'boss_created' => $generatedPassword !== null,
                ];
                $this->verifyReportAndMvt($result);
                return $result;
            });

            $this->command?->info('Verified local MaintenanceCase demo workflow: '.json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            if ($generatedPassword !== null) {
                $this->command?->warn('One-time local Boss login (shown only on first creation): '.$result['boss_username'].' / '.$generatedPassword);
            } else {
                $this->command?->line('Boss account already existed; its password was not changed or displayed.');
            }
            $this->command?->line('All photos are synthetic test files labelled NOT A REAL REPAIR. No prices or operation costs were calculated.');
        } catch (Throwable $exception) {
            $nowFiles = $this->demoPhotoFiles($disk);
            foreach (array_diff($nowFiles, $filesBefore) as $path) $disk->delete($path);
            throw $exception;
        } finally {
            foreach ($this->tempUploadPaths as $path) if (is_file($path)) @unlink($path);
        }
    }

    private function assertSafeTarget(): void
    {
        $connection = config('database.default');
        $settings = config("database.connections.{$connection}", []);
        if (! app()->environment('local') || $connection !== 'pgsql'
            || ($settings['host'] ?? null) !== '127.0.0.1'
            || (string) ($settings['port'] ?? '') !== '5432'
            || ($settings['database'] ?? null) !== self::DATABASE) {
            throw new RuntimeException('Refusing to run: expected APP_ENV=local and pgsql 127.0.0.1:5432/alamakreinforcement.');
        }
        $identity = DB::selectOne('SELECT current_database() AS db, host(inet_server_addr()) AS host, inet_server_port() AS port, system_identifier::text AS cluster FROM pg_control_system()');
        if ($identity->db !== self::DATABASE || $identity->host !== '127.0.0.1'
            || (int) $identity->port !== 5432 || $identity->cluster !== self::CLUSTER_ID) {
            throw new RuntimeException('Refusing to run: live PostgreSQL cluster identity does not match the approved local target.');
        }
    }

    private function assertSchemaAndDemoAssets(): void
    {
        foreach (['maintenance_cases', 'boss_managed_groups', 'operation_image', 'operation_supervisors', 'audit_logs'] as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) throw new RuntimeException("Required migrated table [{$table}] is missing.");
        }
        $zone = DB::table('zone')->where('name', self::ZONE_NAME)->first();
        if (! $zone) throw new RuntimeException('The expected TEST Zone does not exist.');
        $risers = DB::table('riser')->where('r_giscode', 'like', 'TEST-RISER-%')->orderBy('r_giscode')->pluck('r_giscode')->all();
        if (count($risers) !== 20 || $risers[0] !== 'TEST-RISER-001' || end($risers) !== 'TEST-RISER-020') {
            throw new RuntimeException('Expected the complete existing TEST-RISER-001..020 set; no substitute risers will be used.');
        }
        if (DB::table('users')->whereIn('username', self::SUPERVISORS)->count() !== 2) {
            throw new RuntimeException('Both exact Demo supervisor accounts must exist before seeding.');
        }
        foreach (['TEST-OPCAT-001', 'TEST-MAT-001'] as $code) {
            $table = str_starts_with($code, 'TEST-OPCAT') ? 'operation_categories' : 'materials';
            if (! DB::table($table)->where('code', $code)->where('is_active', true)->exists()) {
                throw new RuntimeException("Required active synthetic reference [{$code}] is missing.");
            }
        }
    }

    private function assertRisersInsideZone(int $zoneId): void
    {
        $result = DB::selectOne("SELECT ST_IsValid(z.geom) AS valid,
            COUNT(r.id) AS total,
            COUNT(r.id) FILTER (WHERE ST_Covers(z.geom, r.geom)) AS covered,
            COUNT(r.id) FILTER (WHERE NOT ST_IsValid(r.geom)) AS invalid
            FROM zone z CROSS JOIN riser r
            WHERE z.id = ? AND r.r_giscode LIKE 'TEST-RISER-%' GROUP BY z.geom", [$zoneId]);
        if (! $result || ! $result->valid || (int) $result->total !== 20 || (int) $result->covered !== 20 || (int) $result->invalid !== 0) {
            throw new RuntimeException('All 20 test risers must have valid geometry fully covered by the existing TEST Zone.');
        }
    }

    /** @return array{User, ?string} */
    private function boss(Organization $organization, object $zone, GroupUser $primaryGroup): array
    {
        $role = Role::query()->where('name', 'boss')->where('guard_name', 'web')->first();
        if (! $role) throw new RuntimeException('Role boss is missing; run the approved incremental MaintenanceWorkflowAccessSeeder first.');
        foreach (['user.view', 'operation.view', 'operation.edit', 'operation.approve', 'maintenance.case.view', 'report.view', 'map.view'] as $permission) {
            if (! $role->hasPermissionTo($permission, 'web')) throw new RuntimeException("Boss role lacks required existing permission [{$permission}].");
        }

        $boss = User::query()->where('username', self::BOSS_USERNAME)->lockForUpdate()->first();
        $password = null;
        if (! $boss) {
            $password = Str::random(36);
            $boss = User::query()->create([
                'name' => 'Demo Area Manager',
                'username' => self::BOSS_USERNAME,
                'email' => 'demo-maint-boss@alamak.test',
                'password' => $password,
                'role_id' => 1,
                'organization_id' => $organization->id,
                'zone_id' => $zone->id,
                'group_user_id' => $primaryGroup->id,
            ]);
            $boss->assignRole($role);
            app(AuditLogger::class)->record('demo.maintenance_boss_created', $boss, null, [
                'username' => $boss->username, 'organization_id' => $boss->organization_id,
                'zone_id' => $boss->zone_id, 'group_user_id' => $boss->group_user_id,
            ], ['synthetic' => true]);
        } else {
            if (! $boss->hasRole('boss') || (int) $boss->organization_id !== (int) $organization->id
                || (int) $boss->zone_id !== (int) $zone->id || (int) $boss->group_user_id !== (int) $primaryGroup->id) {
                throw new RuntimeException('Existing demo Boss conflicts with the expected synthetic scope; password and account were not changed.');
            }
        }
        return [$boss, $password];
    }

    /** @param list<GroupUser> $groups @param list<User> $supervisors @return array<string, mixed> */
    private function ensureCases(array $groups, array $supervisors, User $boss): array
    {
        $category = OperationCategory::query()->where('code', 'TEST-OPCAT-001')->firstOrFail();
        $materialId = (int) DB::table('materials')->where('code', 'TEST-MAT-001')->value('id');
        $cases = [];
        foreach ([1, 2, 3] as $number) {
            $code = sprintf('TEST-RISER-%03d', $number);
            $riser = Riser::query()->where('r_giscode', $code)->firstOrFail();
            $marker = sprintf('[DEMO-MAINT-WF:CASE-%02d]', $number);
            $case = MaintenanceCase::query()->where('riser_id', $riser->id)->where('description', 'like', $marker.'%')->first();
            if (! $case) {
                $request = $this->request($supervisors[$number === 3 ? 1 : 0], 'POST', '/maintenance-cases', [
                    'riser_id' => $riser->id, 'operation_category_id' => $category->id,
                    'priority' => 'متوسط', 'description' => $marker.' SYNTHETIC TEST ONLY; NOT A REAL REPAIR.',
                    'identified_at' => now()->subDays(3 - $number)->toDateTimeString(),
                ], ['photos_before' => [$this->syntheticPhoto("CASE-{$number}-BEFORE")]]);
                $response = app(MaintenanceCaseController::class)->store($request);
                if ($response->getStatusCode() !== 201) throw new RuntimeException("Workflow rejected creation for {$marker} (HTTP {$response->getStatusCode()}).");
                $case = MaintenanceCase::query()->findOrFail($response->getData(true)['case']['id']);
            }
            if ((int) $case->riser_id !== (int) $riser->id || (int) $case->assigned_group_id !== (int) $groups[$number === 3 ? 1 : 0]->id
                || ! str_starts_with((string) $case->description, $marker)) {
                throw new RuntimeException("Existing marked case [{$marker}] does not match the exact TEST riser/group scope.");
            }

            if ($number > 1 && ! $case->operation()->exists()) {
                $request = $this->request($supervisors[$number === 3 ? 1 : 0], 'POST', '/maintenance-cases/'.$case->id.'/operation', [
                    'operation_date' => now()->subDays(2 - $number)->toDateString(),
                    'operation_category_id' => $category->id,
                    'description' => $marker.' SYNTHETIC TEST REPAIR; NOT A REAL REPAIR.',
                    'materials' => [$materialId => $number === 2 ? '0.75' : '1.25'],
                ], ['photos_after' => [$this->syntheticPhoto("CASE-{$number}-AFTER")]]);
                $response = app(MaintenanceCaseController::class)->complete($request, $case);
                if ($response->getStatusCode() !== 201) throw new RuntimeException("Workflow rejected operation for {$marker} (HTTP {$response->getStatusCode()}).");
                $case->refresh();
            }

            if ($number === 2 && $case->status === MaintenanceCase::STATUS_SUBMITTED) {
                $request = $this->request($boss, 'POST', '/maintenance-cases/'.$case->id.'/review', ['decision' => 'approved', 'comment' => 'Synthetic workflow test review.']);
                $response = app(MaintenanceCaseController::class)->review($request, $case);
                if ($response->getStatusCode() !== 200) throw new RuntimeException('Boss review failed for the synthetic completed case.');
                $case->refresh();
            }

            if ($number === 3 && ! DB::table('audit_logs')->where('event', 'maintenance_case.area_manager_edited')
                ->where('auditable_id', $case->id)->exists()) {
                $request = $this->request($boss, 'PUT', '/maintenance-cases/'.$case->id, [
                    'description' => $marker.' SYNTHETIC TEST ONLY; Boss reviewed this demo report. NOT A REAL REPAIR.',
                    'materials' => [$materialId => '1.25'],
                ]);
                $response = app(MaintenanceCaseController::class)->update($request, $case);
                if ($response->getStatusCode() !== 200) throw new RuntimeException('Boss report edit failed for the submitted synthetic case.');
                $case->refresh();
            }

            $expected = [1 => MaintenanceCase::STATUS_OPEN, 2 => MaintenanceCase::STATUS_COMPLETED, 3 => MaintenanceCase::STATUS_SUBMITTED][$number];
            if ($case->status !== $expected || OperationCategory::query()->whereKey($category->id)->doesntExist()) {
                throw new RuntimeException("Case {$marker} is not in its required state {$expected}.");
            }
            $operation = $case->operation()->first();
            if (($number === 1) !== ($operation === null)) throw new RuntimeException("Unexpected operation presence for {$marker}.");
            if ($operation && ((float) $operation->total_cost !== 0.0
                || (float) $operation->operationMaterial()->firstOrFail()->pivot->qty <= 0)) {
                throw new RuntimeException("Operation or quantity check failed for {$marker}; no cost calculation is allowed.");
            }
            if ($case->photos()->count() !== ($number === 1 ? 1 : 2)) throw new RuntimeException("Synthetic photo count mismatch for {$marker}.");
            if (! DB::table('audit_logs')->where('event', 'maintenance_case.created')->where('auditable_id', $case->id)->exists()) {
                throw new RuntimeException("Creation audit is missing for {$marker}.");
            }
            if ($number > 1 && ! DB::table('audit_logs')->where('event', 'maintenance_case.submitted')->where('auditable_id', $case->id)->exists()) {
                throw new RuntimeException("Submission audit is missing for {$marker}.");
            }
            if ($number === 3 && (! DB::table('audit_logs')->where('event', 'maintenance_case.area_manager_edited')->where('auditable_id', $case->id)->exists()
                || ! DB::table('audit_logs')->where('event', 'operation.materials_updated')->where('operation_id', $operation?->id)->exists())) {
                throw new RuntimeException('Boss case/material edit audit is missing for the submitted synthetic case.');
            }
            $cases[$marker] = ['case_id' => (int) $case->id, 'riser' => $code, 'status' => $case->status, 'operation_id' => $operation?->id];
        }
        return $cases;
    }

    private function request(User $actor, string $method, string $uri, array $data, array $files = []): Request
    {
        $request = Request::create($uri, $method, $data, [], $files, [
            'HTTP_ACCEPT' => 'application/json', 'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'Local synthetic workflow seeder',
        ]);
        app()->instance('request', $request);
        $request->setUserResolver(fn ($guard = null): User => $actor);
        if ($request->user() !== $actor) throw new RuntimeException('Could not bind the synthetic authenticated actor to the workflow request.');
        $request->attributes->set('_audit_request_id', (string) Str::uuid());
        return $request;
    }

    private function syntheticPhoto(string $label): UploadedFile
    {
        if (! function_exists('imagecreatetruecolor')) throw new RuntimeException('GD is required to generate clearly labelled synthetic PNGs.');
        $image = imagecreatetruecolor(640, 240);
        $background = imagecolorallocate($image, 248, 238, 190);
        $ink = imagecolorallocate($image, 25, 45, 70);
        imagefill($image, 0, 0, $background);
        imagestring($image, 5, 16, 50, 'SYNTHETIC TEST ONLY', $ink);
        imagestring($image, 5, 16, 95, 'NOT A REAL REPAIR', $ink);
        imagestring($image, 4, 16, 145, $label, $ink);
        $temp = tempnam(sys_get_temp_dir(), 'alamak-demo-');
        $this->tempUploadPaths[] = $temp;
        imagepng($image, $temp);
        imagedestroy($image);
        return new UploadedFile($temp, $label.'-SYNTHETIC-NOT-REAL.png', 'image/png', null, true);
    }

    private function verifyReportAndMvt(array $result): void
    {
        $boss = User::query()->findOrFail($result['boss_id']);
        $request = Request::create('/reports/data', 'GET', ['dataset' => 'maintenance_cases']);
        $request->setUserResolver(fn ($guard = null): User => $boss);
        $report = app(ReportService::class)->generate($request);
        if (($report['pagination']['total'] ?? null) < 3) throw new RuntimeException('Boss preview does not include all three scoped test cases.');

        foreach (['open', 'completed', 'submitted'] as $status) {
            $tileRequest = Request::create('/tiles/riser/12/2611/1636.pbf', 'GET', ['case_status' => $status]);
            $tileRequest->setUserResolver(fn ($guard = null): User => $boss);
            $tile = app(TileController::class)->tile($tileRequest, 'riser', 12, 2611, 1636);
            if ($tile->getStatusCode() !== 200 || $tile->headers->get('Content-Type') !== 'application/x-protobuf' || $tile->getContent() === '') {
                throw new RuntimeException("MVT did not return the expected nonempty {$status} tile for the demo risers.");
            }
        }
        if (DB::table('maintenance_cases')->where('description', 'like', '[DEMO-MAINT-WF:%')->count() !== 3) {
            throw new RuntimeException('Expected exactly three explicitly marked synthetic workflow cases.');
        }
    }

    private function superadminId(): int
    {
        return (int) User::query()->whereHas('roles', fn ($q) => $q->where('name', 'superadmin')->where('guard_name', 'web'))->orderBy('id')->value('id');
    }

    private function superadminSnapshot(): ?string
    {
        $admin = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'superadmin')->where('guard_name', 'web'))->orderBy('id')->first();
        return $admin ? json_encode([$admin->id, $admin->name, $admin->username, $admin->email, $admin->organization_id, $admin->zone_id, $admin->group_user_id], JSON_THROW_ON_ERROR) : null;
    }

    private function testOperationCosts(): array
    {
        return DB::table('operations')->join('riser', 'riser.id', '=', 'operations.riser_id')
            ->whereIn('riser.r_giscode', ['TEST-RISER-001', 'TEST-RISER-002', 'TEST-RISER-003'])
            ->orderBy('operations.id')->pluck('operations.total_cost', 'operations.id')->all();
    }

    private function operationCostsUnchanged(array $before): bool
    {
        $after = $this->testOperationCosts();
        foreach ($before as $id => $amount) {
            if (! array_key_exists($id, $after) || (string) $after[$id] !== (string) $amount) return false;
        }
        return true;
    }

    private function demoPhotoFiles($disk): array
    {
        return $disk->allFiles('maintenance-case-photos');
    }
}
