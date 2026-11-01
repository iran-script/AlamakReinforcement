<?php

namespace Tests\Feature;

use App\Models\GroupUser;
use App\Models\Material;
use App\Models\MaintenanceCase;
use App\Models\OperationImage;
use App\Models\OperationCategory;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MaintenanceCaseWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.connections.'.config('database.default'));
        $identity = DB::selectOne('SELECT current_database() AS database_name, host(inet_server_addr()) AS server_host, inet_server_port() AS server_port, system_identifier::text AS cluster_id FROM pg_control_system()');
        $this->assertSame('testing', app()->environment(), 'Workflow tests must run in testing environment.');
        $this->assertSame('pgsql', config('database.default'), 'Workflow tests require PostgreSQL.');
        $this->assertSame('alamaktest', $identity->database_name, 'Refusing to run workflow tests against a non-test database.');
        $this->assertSame('127.0.0.1', $identity->server_host);
        $this->assertSame(55432, (int) $identity->server_port);
        $this->assertSame('7703134720547896280', $identity->cluster_id, 'Refusing to run workflow tests against an unverified PostgreSQL cluster.');
        $this->assertSame('alamaktest', $connection['database'] ?? null);

        if (DB::getDriverName() !== 'pgsql'
            || ! DB::selectOne("select exists(select 1 from pg_extension where extname = 'postgis') as installed")->installed) {
            $this->markTestSkipped('The maintenance workflow requires the isolated PostgreSQL/PostGIS test database.');
        }
    }

    public function test_mobile_case_submission_requires_idempotency_key(): void
    {
        [$organization, $zone, $group] = $this->scope('mobile-idempotency');
        $supervisor = $this->user('mobile-idempotency-supervisor', 'supervisor', $organization, $zone, $group);
        $riserId = $this->riser($zone, 'MOBILE-IDEMPOTENCY-'.uniqid());
        $token = $supervisor->createToken('mobile-idempotency-test', ['mobile:supervisor'])->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/supervisor/cases', [
            'riser_id' => $riserId,
            'priority' => "\u{0645}\u{062A}\u{0648}\u{0633}\u{0637}",
            'identified_at' => now()->toDateTimeString(),
        ])->assertUnprocessable()->assertJsonValidationErrors(['client_uuid']);
    }

    public function test_supervisor_can_mark_only_visible_risers_and_unprivileged_user_cannot(): void
    {
        [$organization, $zone, $group] = $this->scope('field-entry');
        $supervisor = $this->user('field-supervisor', 'supervisor', $organization, $zone, $group);
        $riserId = $this->riser($zone, 'FIELD-RISER-'.uniqid());
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        Storage::fake('local');

        $payload = [
            'riser_id' => $riserId,
            'operation_category_id' => $category->id,
            'priority' => 'زیاد',
            'description' => 'Synthetic field fault',
            'identified_at' => '2026-10-10 10:30:00',
        ];
        $created = $this->actingAs($supervisor)->post('/maintenance-cases', $payload + [
            'photos_before' => [UploadedFile::fake()->image('before.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated();
        $caseId = $created->json('case.id');

        $this->assertDatabaseHas('maintenance_cases', [
            'id' => $caseId,
            'riser_id' => $riserId,
            'reported_by' => $supervisor->id,
            'assigned_group_id' => $group->id,
            'status' => MaintenanceCase::STATUS_OPEN,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'maintenance_case.created',
            'auditable_id' => $caseId,
            'actor_id' => $supervisor->id,
            'actor_role' => 'supervisor',
            'riser_id' => $riserId,
        ]);
        $this->assertNotEmpty(DB::table('audit_logs')->where('auditable_id', $caseId)->value('request_id'));
        $photo = OperationImage::query()->where('maintenance_case_id', $caseId)->firstOrFail();
        Storage::disk('local')->assertExists($photo->path);
        $this->actingAs($supervisor)->get(route('maintenance-cases.photos.show', $photo))->assertOk();
        $this->actingAs($supervisor)->get('/maintenance-cases')->assertOk()->assertSee('پرونده‌های تعمیر');

        $unauthorized = $this->user('field-unauthorized', 'worker', $organization, $zone, $group);
        $this->actingAs($unauthorized)->postJson('/maintenance-cases', $payload)->assertForbidden();

        [, $otherZone, $otherGroup] = $this->scope('field-entry-foreign');
        $otherSupervisor = $this->user('field-supervisor-foreign', 'supervisor', $organization, $otherZone, $otherGroup);
        $this->actingAs($otherSupervisor)->postJson('/maintenance-cases', $payload)->assertForbidden();
        $this->actingAs($otherSupervisor)->get(route('maintenance-cases.photos.show', $photo))->assertNotFound();
        $this->actingAs($supervisor)->deleteJson('/operation/photos/'.$photo->id)->assertOk();
        $this->assertDatabaseHas('audit_logs', ['event' => 'operation.photo_deleted', 'auditable_id' => $photo->id]);
        Storage::disk('local')->assertMissing($photo->path);
    }

    public function test_supervisor_submits_quantities_to_boss_and_boss_reviews_only_own_group_case(): void
    {
        [$organization, $zone, $group] = $this->scope('field-review');
        $supervisor = $this->user('workflow-supervisor', 'supervisor', $organization, $zone, $group);
        $boss = $this->user('workflow-boss', 'boss', $organization, $zone, $group, ['operation.view', 'operation.approve', 'operation.edit']);
        $riserId = $this->riser($zone, 'FIELD-REVIEW-RISER-'.uniqid());
        $category = OperationCategory::create(['title' => 'Repair', 'code' => 'repair-'.uniqid(), 'is_active' => true]);
        $materialCategoryId = DB::table('material_categories')->insertGetId([
            'title' => 'Synthetic category', 'code' => 'category-'.uniqid(), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $material = Material::create([
            'material_category_id' => $materialCategoryId,
            'title' => 'Synthetic pipe', 'code' => 'material-'.uniqid(), 'unit' => 'm',
            'default_price' => 99, 'is_active' => true,
        ]);
        $caseId = $this->actingAs($supervisor)->postJson('/maintenance-cases', [
            'riser_id' => $riserId,
            'operation_category_id' => $category->id,
            'priority' => 'متوسط',
            'description' => 'Synthetic repair needed',
            'identified_at' => '2026-10-11 09:00:00',
        ])->assertCreated()->json('case.id');

        $submitted = $this->actingAs($supervisor)->postJson('/maintenance-cases/'.$caseId.'/operation', [
            'operation_date' => '2026-10-12',
            'description' => 'Synthetic completed repair',
            'materials' => [$material->id => '1.25'],
        ])->assertCreated();
        $operationId = $submitted->json('operation.id');

        $this->assertDatabaseHas('operations', [
            'id' => $operationId,
            'maintenance_case_id' => $caseId,
            'group_user_id' => $group->id,
            'total_cost' => 0,
            'status' => 'submitOperation',
        ]);
        $this->assertDatabaseHas('operation_materials', [
            'operation_id' => $operationId,
            'material_id' => $material->id,
            'qty' => 1.25,
            'price' => 0,
            'total' => 0,
        ]);
        $this->assertDatabaseHas('operation_supervisors', [
            'operation_id' => $operationId, 'user_id' => $boss->id, 'status' => 'pending',
        ]);

        $this->actingAs($boss)->putJson('/maintenance-cases/'.$caseId, [
            'description' => 'Area manager corrected report',
            'materials' => [$material->id => '1.5'],
        ])->assertOk();
        $editAudit = DB::table('audit_logs')->where('event', 'maintenance_case.area_manager_edited')
            ->where('auditable_id', $caseId)->first();
        $this->assertNotNull($editAudit);
        $this->assertSame('Synthetic repair needed', json_decode($editAudit->before, true)['description']);
        $this->assertSame('Area manager corrected report', json_decode($editAudit->after, true)['description']);
        $materialAudit = DB::table('audit_logs')->where('event', 'operation.materials_updated')
            ->where('operation_id', $operationId)->latest('id')->first();
        $this->assertSame('1.25', (string) json_decode($materialAudit->before, true)['materials'][0]['qty']);
        $this->assertSame('1.50', (string) json_decode($materialAudit->after, true)['materials'][0]['qty']);
        $admin = $this->user('workflow-audit-admin', 'superadmin', $organization, $zone, $group);
        $this->actingAs($admin)->get('/audit-logs?operation_id='.$operationId)
            ->assertOk()->assertSee('maintenance_case.area_manager_edited')->assertSee('Area manager corrected report');
        try {
            DB::transaction(fn () => DB::table('audit_logs')->where('id', $editAudit->id)->update(['event' => 'tampered']));
            $this->fail('Audit rows must reject direct updates.');
        } catch (\Illuminate\Database\QueryException $exception) {
            $this->assertStringContainsString('append-only', $exception->getMessage());
        }

        $this->actingAs($boss)->postJson('/maintenance-cases/'.$caseId.'/review', [
            'decision' => 'approved',
            'comment' => 'Checked',
        ])->assertOk()->assertJsonPath('status', MaintenanceCase::STATUS_COMPLETED);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'maintenance_case.reviewed',
            'operation_id' => $operationId,
            'actor_id' => $boss->id,
        ]);

        [, $foreignZone, $foreignGroup] = $this->scope('field-review-foreign');
        $foreignBoss = $this->user('foreign-boss', 'boss', $organization, $foreignZone, $foreignGroup, ['operation.view', 'operation.edit']);
        $this->actingAs($foreignBoss)->getJson('/maintenance-cases/'.$caseId)->assertNotFound();
    }

    public function test_boss_scope_and_case_preview_exports_include_cases_without_operations(): void
    {
        [$organization, $zone, $firstGroup] = $this->scope('case-report');
        $secondGroup = GroupUser::create([
            'name' => 'case-report-second-crew', 'description' => 'Synthetic group',
            'organization_id' => $organization->id, 'zone_id' => $zone->id,
        ]);
        [, $foreignZone, $foreignGroup] = $this->scope('case-report-foreign');
        $boss = $this->user('case-report-boss', 'boss', $organization, $zone, $firstGroup, ['operation.view', 'operation.approve', 'operation.edit', 'report.view']);
        $boss->managedGroups()->syncWithoutDetaching([
            $secondGroup->id => ['assigned_by' => $boss->id],
            $foreignGroup->id => ['assigned_by' => $boss->id],
        ]);
        $supervisorA = $this->user('case-report-supervisor-a', 'supervisor', $organization, $zone, $firstGroup);
        $supervisorB = $this->user('case-report-supervisor-b', 'supervisor', $organization, $zone, $secondGroup);
        $riserA = $this->riser($zone, 'CASE-REPORT-A-'.uniqid());
        $riserB = $this->riser($zone, 'CASE-REPORT-B-'.uniqid());
        $caseA = MaintenanceCase::query()->create([
            'riser_id' => $riserA, 'reported_by' => $supervisorA->id, 'assigned_group_id' => $firstGroup->id,
            'status' => MaintenanceCase::STATUS_OPEN, 'priority' => 'high', 'description' => 'Open synthetic case', 'identified_at' => now(),
        ]);
        $caseB = MaintenanceCase::query()->create([
            'riser_id' => $riserB, 'reported_by' => $supervisorB->id, 'assigned_group_id' => $secondGroup->id,
            'status' => MaintenanceCase::STATUS_SUBMITTED, 'priority' => 'normal', 'description' => 'Submitted synthetic case', 'identified_at' => now(),
        ]);

        $this->assertEqualsCanonicalizing([$firstGroup->id, $secondGroup->id], $boss->fresh()->managedGroupIds());
        $this->actingAs($boss)->getJson('/reports/data?dataset=maintenance_cases&columns[0]=riser&columns[1]=status')
            ->assertOk()->assertJsonPath('pagination.total', 2)
            ->assertJsonFragment(['riser' => DB::table('riser')->where('id', $riserA)->value('r_giscode')]);
        $this->actingAs($boss)->getJson('/reports/data?dataset=maintenance_cases&group_user_id='.$secondGroup->id)
            ->assertOk()->assertJsonPath('pagination.total', 1);
        $this->actingAs($boss)->getJson('/reports/data?dataset=maintenance_cases&group_user_id='.$foreignGroup->id)
            ->assertForbidden();
        $this->actingAs($boss)->putJson('/maintenance-cases/'.$caseA->id, ['assigned_group_id' => $foreignGroup->id])
            ->assertForbidden();
        $this->actingAs($boss)->get('/reports/pdf?dataset=maintenance_cases')->assertOk()->assertSee('CASE-REPORT-A');
        $this->actingAs($boss)->get('/reports/excel?dataset=maintenance_cases')->assertOk();
        $this->actingAs($boss)->get('/maintenance-cases')->assertOk()->assertSee('CASE-REPORT-A')->assertSee('CASE-REPORT-B');

        $this->assertDatabaseHas('maintenance_cases', ['id' => $caseA->id]);
        $this->assertDatabaseHas('maintenance_cases', ['id' => $caseB->id]);
    }

    public function test_full_supervisor_boss_case_workflow_with_photos_audit_reports_and_mvt(): void
    {
        [$organization, $zone, $firstGroup] = $this->scope('full-maintenance-flow');
        $secondGroup = GroupUser::create([
            'name' => 'full-maintenance-flow-second-crew', 'description' => 'Synthetic workflow group',
            'organization_id' => $organization->id, 'zone_id' => $zone->id,
        ]);
        $supervisorA = $this->user('full-flow-supervisor-a', 'supervisor', $organization, $zone, $firstGroup);
        $supervisorB = $this->user('full-flow-supervisor-b', 'supervisor', $organization, $zone, $secondGroup);
        $boss = $this->user('full-flow-boss', 'boss', $organization, $zone, $firstGroup, [
            'operation.view', 'operation.approve', 'operation.edit', 'report.view', 'map.view', 'user.view', 'maintenance.case.view',
        ]);
        $boss->managedGroups()->syncWithoutDetaching([
            $secondGroup->id => ['assigned_by' => $boss->id],
        ]);
        $admin = $this->user('full-flow-admin', 'superadmin', $organization, $zone, $firstGroup);

        $category = OperationCategory::create(['title' => 'Synthetic Repair', 'code' => 'full-flow-'.uniqid(), 'is_active' => true]);
        $materialCategoryId = DB::table('material_categories')->insertGetId([
            'title' => 'Synthetic workflow materials', 'code' => 'full-flow-cat-'.uniqid(), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $material = Material::create([
            'material_category_id' => $materialCategoryId, 'title' => 'Synthetic coupling',
            'code' => 'full-flow-mat-'.uniqid(), 'unit' => 'عدد', 'default_price' => 0, 'is_active' => true,
        ]);
        $risers = [
            $this->riser($zone, 'FULL-FLOW-OPEN-'.uniqid()),
            $this->riser($zone, 'FULL-FLOW-REPAIRED-'.uniqid()),
            $this->riser($zone, 'FULL-FLOW-SUBMITTED-'.uniqid()),
        ];

        $makeCase = function (User $supervisor, int $riserId, string $label) use ($category): int {
            return (int) $this->actingAs($supervisor)->post('/maintenance-cases', [
                'riser_id' => $riserId,
                'operation_category_id' => $category->id,
                'priority' => 'متوسط',
                'description' => 'SYNTHETIC TEST ONLY: '.$label,
                'identified_at' => '2026-10-20 09:00:00',
                'photos_before' => [UploadedFile::fake()->image('SYNTHETIC-TEST-NOT-REAL-BEFORE.png', 320, 180)],
            ], ['Accept' => 'application/json'])->assertCreated()->json('case.id');
        };

        $openCaseId = $makeCase($supervisorA, $risers[0], 'CASE-OPEN');
        $repairedCaseId = $makeCase($supervisorA, $risers[1], 'CASE-REPAIRED');
        $submittedCaseId = $makeCase($supervisorB, $risers[2], 'CASE-SUBMITTED');

        $complete = function (User $supervisor, int $caseId, string $qty) use ($category, $material): int {
            return (int) $this->actingAs($supervisor)->post('/maintenance-cases/'.$caseId.'/operation', [
                'operation_date' => '2026-10-21',
                'operation_category_id' => $category->id,
                'description' => 'SYNTHETIC TEST ONLY: completed repair',
                'materials' => [$material->id => $qty],
                'photos_after' => [UploadedFile::fake()->image('SYNTHETIC-TEST-NOT-REAL-AFTER.png', 320, 180)],
            ], ['Accept' => 'application/json'])->assertCreated()->json('operation.id');
        };

        $completedOperationId = $complete($supervisorA, $repairedCaseId, '0.75');
        $submittedOperationId = $complete($supervisorB, $submittedCaseId, '1.25');
        $this->assertDatabaseHas('operation_materials', ['operation_id' => $completedOperationId, 'qty' => 0.75, 'price' => 0, 'total' => 0]);
        $this->assertDatabaseHas('operations', ['id' => $completedOperationId, 'total_cost' => 0]);
        $this->assertDatabaseHas('operations', ['id' => $submittedOperationId, 'total_cost' => 0]);

        $this->actingAs($boss)->postJson('/maintenance-cases/'.$repairedCaseId.'/review', [
            'decision' => 'approved', 'comment' => 'Synthetic test review only',
        ])->assertOk()->assertJsonPath('status', MaintenanceCase::STATUS_COMPLETED);

        $this->assertDatabaseHas('maintenance_cases', ['id' => $openCaseId, 'status' => MaintenanceCase::STATUS_OPEN]);
        $this->assertDatabaseHas('maintenance_cases', ['id' => $repairedCaseId, 'status' => MaintenanceCase::STATUS_COMPLETED]);
        $this->assertDatabaseHas('maintenance_cases', ['id' => $submittedCaseId, 'status' => MaintenanceCase::STATUS_SUBMITTED]);
        $this->assertSame(1, OperationImage::query()->where('maintenance_case_id', $openCaseId)->count());
        $this->assertSame(2, OperationImage::query()->where('maintenance_case_id', $repairedCaseId)->count());
        $this->assertSame(2, OperationImage::query()->where('maintenance_case_id', $submittedCaseId)->count());

        foreach ([$openCaseId, $repairedCaseId, $submittedCaseId] as $caseId) {
            $this->assertDatabaseHas('audit_logs', ['event' => 'maintenance_case.created', 'auditable_id' => $caseId]);
        }
        $this->assertDatabaseHas('audit_logs', ['event' => 'maintenance_case.submitted', 'auditable_id' => $repairedCaseId]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'maintenance_case.reviewed', 'auditable_id' => $repairedCaseId]);

        $this->actingAs($boss)->getJson('/reports/data?dataset=maintenance_cases')
            ->assertOk()->assertJsonPath('pagination.total', 3);
        $this->actingAs($boss)->getJson('/reports/data?dataset=maintenance_cases&zone='.$zone->id)
            ->assertOk()->assertJsonPath('pagination.total', 3);
        [, $foreignZone, $foreignGroup] = $this->scope('full-maintenance-flow-foreign');
        $this->actingAs($boss)->getJson('/reports/data?dataset=maintenance_cases&group_user_id='.$foreignGroup->id)
            ->assertForbidden();
        $this->actingAs($boss)->getJson('/reports/data?dataset=maintenance_cases&zone='.$foreignZone->id)
            ->assertForbidden();
        $this->actingAs($admin)->get('/audit-logs?')->assertOk()
            ->assertSee('maintenance_case.created')->assertSee('maintenance_case.reviewed');

        $outsider = $this->user('full-flow-outsider', 'boss', $organization, $zone, $firstGroup, ['operation.view', 'operation.approve', 'operation.edit', 'report.view']);
        $outsider->managedGroups()->detach();
        $this->actingAs($outsider)->getJson('/maintenance-cases/'.$submittedCaseId)->assertForbidden();
        $this->actingAs($outsider)->getJson('/reports/data?dataset=maintenance_cases')
            ->assertOk()->assertJsonPath('pagination.total', 0);
        $this->actingAs($outsider)->get('/tiles/riser/12/2605/1636.pbf?case_status=submitted')
            ->assertOk()->assertContent('');
        $this->actingAs($boss)->get('/tiles/riser/12/2605/1636.pbf?zone_id='.$foreignZone->id.'&case_status=submitted')->assertForbidden();

        // All three risers are clustered in the fixture zone; the existing MVT endpoint must return a tile.
        $tile = $this->actingAs($boss)->get('/tiles/riser/12/2605/1636.pbf?case_status=submitted');
        $tile->assertOk()->assertHeader('Content-Type', 'application/x-protobuf');
        $this->assertNotEmpty($tile->getContent());
    }

    private function scope(string $prefix): array
    {
        $organization = Organization::create(['code' => $prefix.'-'.uniqid(), 'name' => $prefix]);
        $x = str_contains($prefix, 'foreign') ? 50 : 49;
        $y = str_contains($prefix, 'foreign') ? 35 : 34;
        $zoneId = DB::selectOne(
            "insert into zone (name, organization_id, geom, created_at, updated_at)
             values (?, ?, ST_MakeEnvelope(?, ?, ?, ?, 4326), now(), now()) returning id",
            [$prefix.'-zone', $organization->id, $x, $y, $x + 0.02, $y + 0.02]
        )->id;
        $zone = \App\Models\Zone::query()->findOrFail($zoneId);
        $group = GroupUser::create([
            'name' => $prefix.'-crew', 'description' => 'Synthetic group',
            'organization_id' => $organization->id, 'zone_id' => $zone->id,
        ]);

        return [$organization, $zone, $group];
    }

    private function user(string $name, string $roleName, Organization $organization, $zone, GroupUser $group, array $permissions = []): User
    {
        $user = User::create([
            'organization_id' => $organization->id,
            'zone_id' => $zone->id,
            'group_user_id' => $group->id,
            'name' => $name,
            'username' => $name.'-'.uniqid(),
            'password' => 'synthetic-password',
            'role_id' => 1,
        ]);
        $role = Role::findOrCreate($roleName, 'web');
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $user->assignRole($role);
        if ($roleName === 'boss') {
            $user->managedGroups()->syncWithoutDetaching([$group->id => ['assigned_by' => $user->id]]);
        }

        return $user;
    }

    private function riser($zone, string $code): int
    {
        $x = str_contains($zone->name, 'foreign') ? 50.01 : 49.01;
        $y = str_contains($zone->name, 'foreign') ? 35.01 : 34.01;
        return (int) DB::selectOne(
            "insert into riser (r_giscode, giscode, status, geom, geom_3857, created_at, updated_at)
             values (?, ?, 'default', ST_SetSRID(ST_MakePoint(?, ?), 4326),
                     ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857), now(), now()) returning id",
            [$code, $code, $x, $y, $x, $y]
        )->id;
    }
}
