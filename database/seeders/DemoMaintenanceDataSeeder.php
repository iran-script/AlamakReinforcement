<?php

namespace Database\Seeders;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Operation;
use App\Models\OperationCategory;
use App\Models\OperationMaterial;
use App\Models\OperationSupervisor;
use App\Models\Riser;
use App\Models\User;
use App\Models\WorkflowTransfer;
use App\Services\WorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DemoMaintenanceDataSeeder extends Seeder
{
    private const DATABASE = 'alamakreinforcement';

    private const CLUSTER_ID = '6882458043277858192';

    private const ZONE_NAME = 'TEST-ZONE-RISER-DEMO';

    private const RISER_PREFIX = 'TEST-RISER-';

    private const CATEGORY_PREFIX = 'TEST-MATCAT-';

    private const MATERIAL_PREFIX = 'TEST-MAT-';

    private const OPERATION_CATEGORY_PREFIX = 'TEST-OPCAT-';

    private const USER_PREFIX = 'demo-maint-';

    private const OPERATION_PREFIX = 'DEMO-MAINT-OP-';

    private const CONSUMPTION_PREFIX = 'DEMO-MAINT-CONSUMPTION-';

    /** Permissions defined by RolePermissionSeeder and needed by these demo actors. */
    private const USER_PERMISSIONS = [
        'operator' => ['dashboard.view', 'riser.view', 'operation.view', 'operation.create'],
        'supervisor' => ['dashboard.view', 'riser.view', 'operation.view', 'operation.approve'],
    ];

    private const MATERIAL_CATEGORIES = [
        ['code' => 'TEST-MATCAT-001', 'title' => 'لوله و قطعات لوله‌کشی'],
        ['code' => 'TEST-MATCAT-002', 'title' => 'اتصالات'],
        ['code' => 'TEST-MATCAT-003', 'title' => 'شیرآلات'],
        ['code' => 'TEST-MATCAT-004', 'title' => 'حفاظت و مقاوم‌سازی'],
        ['code' => 'TEST-MATCAT-005', 'title' => 'تجهیزات نصب'],
        ['code' => 'TEST-MATCAT-006', 'title' => 'سایر ملزومات تعمیراتی'],
    ];

    private const MATERIALS = [
        ['title' => 'لوله فولادی کوتاه', 'unit' => 'متر'],
        ['title' => 'لوله فولادی گالوانیزه', 'unit' => 'متر'],
        ['title' => 'لوله رابط علمک', 'unit' => 'متر'],
        ['title' => 'غلاف محافظ لوله', 'unit' => 'متر'],
        ['title' => 'لوله محافظ پلیمری', 'unit' => 'متر'],
        ['title' => 'زانویی فولادی', 'unit' => 'عدد'],
        ['title' => 'سه‌راهی فولادی', 'unit' => 'عدد'],
        ['title' => 'تبدیل فولادی', 'unit' => 'عدد'],
        ['title' => 'بوشن فولادی', 'unit' => 'عدد'],
        ['title' => 'مهره ماسوره', 'unit' => 'عدد'],
        ['title' => 'شیر قطع و وصل', 'unit' => 'عدد'],
        ['title' => 'شیر توپی', 'unit' => 'عدد'],
        ['title' => 'شیر یک‌طرفه', 'unit' => 'عدد'],
        ['title' => 'دسته شیر یدکی', 'unit' => 'عدد'],
        ['title' => 'درپوش شیر', 'unit' => 'عدد'],
        ['title' => 'نوار عایق محافظ', 'unit' => 'متر'],
        ['title' => 'پوشش ضدخوردگی', 'unit' => 'کیلوگرم'],
        ['title' => 'عایق محافظ اتصال', 'unit' => 'عدد'],
        ['title' => 'رنگ محافظ فلز', 'unit' => 'کیلوگرم'],
        ['title' => 'ماستیک آب‌بندی', 'unit' => 'کیلوگرم'],
        ['title' => 'بست نگهدارنده لوله', 'unit' => 'عدد'],
        ['title' => 'پایه نصب علمک', 'unit' => 'عدد'],
        ['title' => 'صفحه مهار فلزی', 'unit' => 'عدد'],
        ['title' => 'پیچ فولادی', 'unit' => 'عدد'],
        ['title' => 'مهره فولادی', 'unit' => 'عدد'],
        ['title' => 'واشر تخت', 'unit' => 'عدد'],
        ['title' => 'واشر آب‌بندی', 'unit' => 'عدد'],
        ['title' => 'رابط رزوه‌ای', 'unit' => 'عدد'],
        ['title' => 'ماده آب‌بندی رزوه', 'unit' => 'عدد'],
        ['title' => 'قطعه محافظ سر علمک', 'unit' => 'عدد'],
    ];

    private const OPERATION_CATEGORIES = [
        ['code' => 'TEST-OPCAT-001', 'title' => 'تعمیر علمک'],
        ['code' => 'TEST-OPCAT-002', 'title' => 'تعویض شیر'],
        ['code' => 'TEST-OPCAT-003', 'title' => 'تعویض اتصالات'],
        ['code' => 'TEST-OPCAT-004', 'title' => 'مقاوم‌سازی علمک'],
        ['code' => 'TEST-OPCAT-005', 'title' => 'بازسازی علمک'],
        ['code' => 'TEST-OPCAT-006', 'title' => 'رفع نشتی'],
        ['code' => 'TEST-OPCAT-007', 'title' => 'اصلاح ارتفاع علمک'],
        ['code' => 'TEST-OPCAT-008', 'title' => 'تعویض قطعه آسیب‌دیده'],
    ];

    public function run(): void
    {
        $this->assertSafeTarget();
        $this->assertRequiredSchema();

        $result = DB::transaction(function (): array {
            DB::selectOne("SELECT pg_advisory_xact_lock(hashtext('demo-maintenance-data-seeder'))");
            $this->assertSafeTarget();

            $beforeSuperadmin = $this->superadminSnapshot();
            [$zone, $risers] = $this->resolveDemoAssets();
            $this->assertNoUnexpectedDemoRows();

            $materialCategories = $this->ensureMaterialCategories();
            $materials = $this->ensureMaterials($materialCategories);
            $operationCategories = $this->ensureOperationCategories();
            [$operators, $supervisors] = $this->ensureDemoUsers($zone->id);
            $operations = $this->ensureOperations($risers, $operators, $operationCategories);
            $this->ensureMaterialUsage($operations, $materials);
            $this->ensureSupervisorHistory($operations, $supervisors);

            $this->assertFinalData($zone->id, $risers);

            if ($beforeSuperadmin !== $this->superadminSnapshot()) {
                throw new RuntimeException('The existing Superadmin changed; transaction aborted.');
            }

            return $this->summary($zone->id);
        });

        $this->command?->info(sprintf(
            'Synthetic maintenance data ready: %d categories, %d materials, %d operation types, %d users, %d operations, %d material lines, %d supervisor decisions.',
            $result['material_categories'],
            $result['materials'],
            $result['operation_categories'],
            $result['users'],
            $result['operations'],
            $result['material_lines'],
            $result['supervisor_rows'],
        ));
        $this->command?->line(sprintf(
            'Demo zone id: %d; demo risers retained: %d; operations by stored status: %s.',
            $result['zone_id'],
            $result['demo_risers'],
            json_encode($result['operation_statuses'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        ));
        $this->command?->line(sprintf(
            'Operation identifiers: %s through %s; supervisor statuses: %s; operation total_cost sum (stored values only): %s.',
            self::OPERATION_PREFIX.'001',
            self::OPERATION_PREFIX.'040',
            json_encode($result['supervisor_statuses'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            $result['stored_total_cost_sum'],
        ));
        $this->command?->line('No password, material price, computed material total, or Superadmin credential is printed.');
    }

    private function assertSafeTarget(): void
    {
        if (config('app.env') !== 'local') {
            throw new RuntimeException('DemoMaintenanceDataSeeder is allowed only when APP_ENV=local.');
        }

        $connection = config('database.default');
        $settings = config("database.connections.{$connection}", []);
        if ($connection !== 'pgsql'
            || ($settings['host'] ?? null) !== '127.0.0.1'
            || (string) ($settings['port'] ?? '') !== '5432'
            || ($settings['database'] ?? null) !== self::DATABASE) {
            throw new RuntimeException('Refusing to seed: expected pgsql 127.0.0.1:5432/alamakreinforcement.');
        }

        $identity = DB::connection($connection)->selectOne(
            'SELECT current_database() AS database_name,
                    host(inet_server_addr()) AS server_host,
                    inet_server_port() AS server_port,
                    current_user AS database_user'
        );
        $cluster = DB::connection($connection)->selectOne(
            'SELECT system_identifier::text AS cluster_id FROM pg_control_system()'
        );

        if (($identity->database_name ?? null) !== self::DATABASE
            || ($identity->server_host ?? null) !== '127.0.0.1'
            || (int) ($identity->server_port ?? 0) !== 5432
            || ($cluster->cluster_id ?? null) !== self::CLUSTER_ID) {
            throw new RuntimeException('Refusing to seed: live PostgreSQL identity does not match the approved local cluster.');
        }

        $this->command?->line(sprintf(
            'Verified local target: %s@%s:%d/%s, cluster %s.',
            $identity->database_user,
            $identity->server_host,
            $identity->server_port,
            $identity->database_name,
            $cluster->cluster_id,
        ));
    }

    private function assertRequiredSchema(): void
    {
        $required = [
            'material_categories' => ['id', 'title', 'code', 'is_active', 'sort'],
            'materials' => ['id', 'material_category_id', 'title', 'code', 'unit', 'default_price', 'is_active'],
            'operation_categories' => ['id', 'title', 'code', 'is_active', 'sort'],
            'operations' => ['id', 'riser_id', 'operation_category_id', 'user_id', 'operation_date', 'status', 'priority', 'total_cost', 'description'],
            'operation_materials' => ['id', 'operation_id', 'material_id', 'qty', 'price', 'total', 'description'],
            'operation_supervisors' => ['id', 'operation_id', 'user_id', 'status', 'comment', 'approved_at', 'order'],
            'workflow_transfers' => ['id', 'user_id_from', 'user_id_to', 'riser_id', 'operation_id', 'type', 'comment', 'send_date', 'receive_date'],
            'users' => ['id', 'name', 'username', 'email', 'password', 'role_id', 'zone_id'],
            'riser' => ['id', 'r_giscode', 'giscode', 'status', 'geom', 'geom_3857'],
            'zone' => ['id', 'name', 'geom'],
            'roles' => ['id', 'name', 'guard_name'],
            'permissions' => ['id', 'name', 'guard_name'],
        ];

        foreach ($required as $table => $columns) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required table [{$table}] is missing; migrations will not be run by this seeder.");
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("Required column [{$table}.{$column}] is missing; no schema will be changed.");
                }
            }
        }

        $srids = collect(DB::select(
            'SELECT f_table_name, f_geometry_column, srid FROM geometry_columns
             WHERE f_table_schema = ? AND f_table_name IN (?, ?)',
            ['public', 'riser', 'zone']
        ))->mapWithKeys(fn ($row) => ["{$row->f_table_name}.{$row->f_geometry_column}" => (int) $row->srid]);

        foreach (['riser.geom' => 4326, 'riser.geom_3857' => 3857, 'zone.geom' => 4326] as $key => $srid) {
            if ($srids->get($key) !== $srid) {
                throw new RuntimeException("Expected existing geometry [{$key}] at SRID {$srid}; no schema changes were made.");
            }
        }

        $statusConstraint = DB::selectOne(
            "SELECT pg_get_constraintdef(oid) AS definition
             FROM pg_constraint
             WHERE conrelid = 'operation_supervisors'::regclass
               AND contype = 'c'
               AND pg_get_constraintdef(oid) LIKE '%status%'"
        );
        foreach (['pending', 'approved', 'need_revision'] as $status) {
            if (! $statusConstraint || ! str_contains($statusConstraint->definition, $status)) {
                throw new RuntimeException("The stored supervisor-status contract does not allow [{$status}].");
            }
        }
    }

    /** @return array{0: object, 1: array<int, Riser>} */
    private function resolveDemoAssets(): array
    {
        $zones = DB::table('zone')->where('name', self::ZONE_NAME)->get(['id']);
        if ($zones->count() !== 1) {
            throw new RuntimeException('Expected exactly one existing TEST-ZONE-RISER-DEMO; no zone will be replaced or renamed.');
        }

        $zone = $zones->first();
        $zoneGeometry = DB::selectOne(
            'SELECT ST_SRID(geom) AS srid, ST_IsValid(geom) AS valid,
                    ST_GeometryType(geom) AS geometry_type
             FROM zone WHERE id = ?',
            [$zone->id]
        );
        if (! $zoneGeometry || (int) $zoneGeometry->srid !== 4326 || ! $zoneGeometry->valid
            || ! in_array($zoneGeometry->geometry_type, ['ST_Polygon', 'ST_MultiPolygon'], true)) {
            throw new RuntimeException('The existing synthetic Zone is invalid or has an unexpected SRID; it remains unchanged.');
        }

        $risers = Riser::query()->where('r_giscode', 'like', self::RISER_PREFIX.'%')
            ->orderBy('r_giscode')->get();
        if ($risers->count() !== 20) {
            throw new RuntimeException('Expected the existing 20 TEST-RISER records; no risers will be created or changed.');
        }

        $expectedCodes = array_map(fn (int $number) => self::RISER_PREFIX.sprintf('%03d', $number), range(1, 20));
        if ($risers->pluck('r_giscode')->all() !== $expectedCodes
            || $risers->pluck('giscode')->all() !== $expectedCodes
            || DB::table('riser')->where('giscode', 'like', self::RISER_PREFIX.'%')->count() !== 20) {
            throw new RuntimeException('The synthetic riser code set is incomplete, duplicated, or inconsistent.');
        }

        $outside = DB::table('riser')->whereIn('r_giscode', $expectedCodes)
            ->whereRaw('geom IS NULL OR geom_3857 IS NULL OR NOT ST_IsValid(geom) OR NOT ST_IsValid(geom_3857)')
            ->count();
        $outsideZone = DB::table('riser')->whereIn('r_giscode', $expectedCodes)
            ->whereRaw('NOT ST_Covers((SELECT geom FROM zone WHERE id = ?), riser.geom)', [$zone->id])
            ->count();
        if ($outside !== 0 || $outsideZone !== 0) {
            throw new RuntimeException('One or more retained test risers have invalid geometry or fall outside the demo Zone.');
        }

        return [$zone, $risers->all()];
    }

    private function assertNoUnexpectedDemoRows(): void
    {
        $expectedCategoryCodes = array_column(self::MATERIAL_CATEGORIES, 'code');
        $expectedOperationCodes = array_column(self::OPERATION_CATEGORIES, 'code');
        $expectedMaterialCodes = array_map(fn (int $i) => self::MATERIAL_PREFIX.sprintf('%03d', $i), range(1, 30));
        $expectedUsernames = [
            'demo-maint-executor-01', 'demo-maint-executor-02', 'demo-maint-recorder-01',
            'demo-maint-supervisor-01', 'demo-maint-supervisor-02',
        ];
        $expectedOperationMarkers = array_map(fn (int $i) => self::OPERATION_PREFIX.sprintf('%03d', $i), range(1, 40));

        $sets = [
            ['table' => 'material_categories', 'column' => 'code', 'prefix' => self::CATEGORY_PREFIX, 'expected' => $expectedCategoryCodes],
            ['table' => 'materials', 'column' => 'code', 'prefix' => self::MATERIAL_PREFIX, 'expected' => $expectedMaterialCodes],
            ['table' => 'operation_categories', 'column' => 'code', 'prefix' => self::OPERATION_CATEGORY_PREFIX, 'expected' => $expectedOperationCodes],
            ['table' => 'users', 'column' => 'username', 'prefix' => self::USER_PREFIX, 'expected' => $expectedUsernames],
        ];

        foreach ($sets as $set) {
            $found = DB::table($set['table'])->where($set['column'], 'like', $set['prefix'].'%')->pluck($set['column'])->all();
            if (array_diff($found, $set['expected']) !== [] || count($found) !== count(array_unique($found))) {
                throw new RuntimeException("Unexpected or duplicated synthetic identifiers in [{$set['table']}]; existing rows were left untouched.");
            }
        }

        $operationMarkers = DB::table('operations')->where('description', 'like', self::OPERATION_PREFIX.'%')
            ->pluck('description')->map(fn ($value) => explode(' | ', $value, 2)[0])->all();
        if (array_diff($operationMarkers, $expectedOperationMarkers) !== []
            || count($operationMarkers) !== count(array_unique($operationMarkers))) {
            throw new RuntimeException('Unexpected or duplicated demo operation marker found; no existing row will be changed.');
        }

        $consumptionMarkers = DB::table('operation_materials')->where('description', 'like', self::CONSUMPTION_PREFIX.'%')
            ->pluck('description')->all();
        $expectedConsumption = [];
        for ($operation = 1; $operation <= 40; $operation++) {
            $lineCount = $operation <= 20 ? 3 : 2;
            foreach (range(1, $lineCount) as $line) {
                $expectedConsumption[] = sprintf('%s%03d-%d', self::CONSUMPTION_PREFIX, $operation, $line);
            }
        }
        if (array_diff($consumptionMarkers, $expectedConsumption) !== []
            || count($consumptionMarkers) !== count(array_unique($consumptionMarkers))) {
            throw new RuntimeException('Unexpected or duplicated demo material-consumption marker found.');
        }
    }

    /** @return array<string, MaterialCategory> */
    private function ensureMaterialCategories(): array
    {
        $categories = [];
        foreach (self::MATERIAL_CATEGORIES as $index => $definition) {
            $category = MaterialCategory::query()->where('code', $definition['code'])->first();
            if (! $category) {
                $category = MaterialCategory::query()->create([
                    'code' => $definition['code'],
                    'title' => $definition['title'],
                    'description' => 'Synthetic maintenance demo category; not a real inventory classification.',
                    'sort' => 900 + $index,
                    'is_active' => true,
                ]);
            }
            $categories[$definition['code']] = $category;
        }

        return $categories;
    }

    /** @return array<int, Material> */
    private function ensureMaterials(array $categories): array
    {
        $materials = [];
        foreach (self::MATERIALS as $index => $definition) {
            $code = self::MATERIAL_PREFIX.sprintf('%03d', $index + 1);
            $categoryCode = self::MATERIAL_CATEGORIES[intdiv($index, 5)]['code'];
            $material = Material::query()->where('code', $code)->first();

            if (! $material) {
                $material = Material::query()->create([
                    'material_category_id' => $categories[$categoryCode]->id,
                    'title' => $definition['title'],
                    'code' => $code,
                    'unit' => $definition['unit'],
                    // Legacy column only: neutral value, deliberately not priced or multiplied.
                    'default_price' => 0,
                    'description' => 'Synthetic test material. No real price is assigned.',
                    'is_active' => true,
                ]);
            } elseif ((int) $material->material_category_id !== (int) $categories[$categoryCode]->id
                || $material->unit !== $definition['unit']) {
                throw new RuntimeException("Existing demo material [{$code}] has a conflicting category or unit; it was not rewritten.");
            }

            $materials[$index + 1] = $material;
        }

        return $materials;
    }

    /** @return array<int, OperationCategory> */
    private function ensureOperationCategories(): array
    {
        $categories = [];
        foreach (self::OPERATION_CATEGORIES as $index => $definition) {
            $category = OperationCategory::query()->where('code', $definition['code'])->first();
            if (! $category) {
                $category = OperationCategory::query()->create([
                    'code' => $definition['code'],
                    'title' => $definition['title'],
                    'description' => 'Synthetic repair type for local development testing.',
                    'sort' => 900 + $index,
                    'is_active' => true,
                ]);
            }
            $categories[$index + 1] = $category;
        }

        return $categories;
    }

    /** @return array{0: list<User>, 1: list<User>} */
    private function ensureDemoUsers(int $zoneId): array
    {
        $roles = [];
        foreach (['operator', 'supervisor'] as $roleName) {
            // These exact role names already exist in RolePermissionSeeder; do not invent roles.
            $roles[$roleName] = Role::findOrCreate($roleName, 'web');
        }

        $permissionNames = array_values(array_unique(array_merge(...array_values(self::USER_PERMISSIONS))));
        $permissions = [];
        foreach ($permissionNames as $permissionName) {
            $permissions[$permissionName] = Permission::findOrCreate($permissionName, 'web');
        }

        $specifications = [
            ['username' => 'demo-maint-executor-01', 'name' => 'Demo Executor 01', 'role' => 'operator'],
            ['username' => 'demo-maint-executor-02', 'name' => 'Demo Executor 02', 'role' => 'operator'],
            ['username' => 'demo-maint-recorder-01', 'name' => 'Demo Operation Recorder', 'role' => 'operator'],
            ['username' => 'demo-maint-supervisor-01', 'name' => 'Demo Supervisor 01', 'role' => 'supervisor'],
            ['username' => 'demo-maint-supervisor-02', 'name' => 'Demo Supervisor 02', 'role' => 'supervisor'],
        ];

        $createdUsers = [];
        foreach ($specifications as $specification) {
            $email = $specification['username'].'@example.test';
            $user = User::query()->where('username', $specification['username'])->first();
            $role = $roles[$specification['role']];

            if (! $user) {
                $user = User::query()->create([
                    'name' => $specification['name'],
                    'username' => $specification['username'],
                    'email' => $email,
                    // The hashed model cast hashes this runtime-only random value once.
                    // It is never logged, returned, or embedded in this source file.
                    'password' => Str::random(80),
                    'role_id' => $role->id,
                    'zone_id' => $zoneId,
                ]);
                $user->assignRole($role);
                $user->givePermissionTo(array_map(
                    fn (string $name) => $permissions[$name],
                    self::USER_PERMISSIONS[$specification['role']],
                ));
            } elseif ((int) $user->zone_id !== $zoneId
                || (int) $user->role_id !== (int) $role->id
                || $user->email !== $email
                || ! $user->hasRole($role)) {
                throw new RuntimeException("Existing demo user [{$specification['username']}] conflicts with the expected role or Zone; it was not changed.");
            }

            $createdUsers[] = $user;
        }

        return [
            array_values(array_filter($createdUsers, fn (User $user) => $user->hasRole('operator'))),
            array_values(array_filter($createdUsers, fn (User $user) => $user->hasRole('supervisor'))),
        ];
    }

    /** @return list<Operation> */
    private function ensureOperations(array $risers, array $operators, array $categories): array
    {
        $risersByCode = collect($risers)->keyBy('r_giscode');
        $riserDistribution = [];
        $operationIndex = 1;
        $riserNumber = 1;
        foreach ([6 => 1, 7 => 2, 4 => 3, 2 => 4] as $riserCount => $operationsPerRiser) {
            for ($riserOffset = 0; $riserOffset < $riserCount; $riserOffset++) {
                for ($operationForRiser = 0; $operationForRiser < $operationsPerRiser; $operationForRiser++) {
                    $riserDistribution[$operationIndex] = $riserNumber;
                    $operationIndex++;
                }
                $riserNumber++;
            }
        }
        if (count($riserDistribution) !== 40 || max($riserDistribution) !== 19) {
            throw new RuntimeException('Internal demo riser distribution did not produce the required 40 operations and one unrepaired riser.');
        }

        $operations = [];
        foreach (range(1, 40) as $index) {
            $marker = self::OPERATION_PREFIX.sprintf('%03d', $index);
            $riserCode = self::RISER_PREFIX.sprintf('%03d', $riserDistribution[$index]);
            $riser = $risersByCode->get($riserCode);
            $operator = $operators[($index - 1) % count($operators)];
            $category = $categories[(($index - 1) % count($categories)) + 1];
            $status = match (true) {
                $index <= 8 => 'submitOperation',
                $index <= 16 => 'approved',
                $index <= 20 => 'need_revision',
                default => '0',
            };
            // Fixed, synthetic months keep repeated runs deterministic while spanning 12 months.
            $operationDate = CarbonImmutable::create(2025, 11, 1)
                ->addMonths(($index - 1) % 12)->addDays(($index * 3) % 20)->toDateString();
            $description = $marker.' | synthetic local maintenance record';
            $operation = Operation::query()->where('description', $description)->first();

            if (! $operation) {
                $createdAt = $index === 40 ? now() : CarbonImmutable::parse($operationDate)->setTime(9, 0);
                $operation = Operation::query()->create([
                    'riser_id' => $riser->id,
                    'operation_category_id' => $category->id,
                    'user_id' => $operator->id,
                    'operation_date' => $operationDate,
                    'start_time' => '09:00:00',
                    'end_time' => '10:30:00',
                    'status' => $status,
                    'priority' => '0',
                    // total_cost omitted so the schema's neutral default remains untouched.
                    'description' => $description,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            } elseif ((int) $operation->riser_id !== (int) $riser->id
                || (int) $operation->operation_category_id !== (int) $category->id
                || (int) $operation->user_id !== (int) $operator->id
                || CarbonImmutable::parse($operation->operation_date)->toDateString() !== $operationDate
                || $operation->status !== $status
                || (float) $operation->total_cost !== 0.0) {
                throw new RuntimeException("Existing synthetic operation [{$marker}] conflicts with the expected links or neutral total_cost; it was not rewritten.");
            }

            $operations[$index] = $operation;
        }

        return $operations;
    }

    private function ensureMaterialUsage(array $operations, array $materials): void
    {
        foreach ($operations as $operationNumber => $operation) {
            $lineCount = $operationNumber <= 20 ? 3 : 2;
            foreach (range(1, $lineCount) as $lineNumber) {
                $materialNumber = ((($operationNumber * 3) + (($lineNumber - 1) * 7) - 1) % 30) + 1;
                $material = $materials[$materialNumber];
                $quantity = [0.5, 1.0, 1.25, 2.0, 3.5][($operationNumber + $lineNumber) % 5];
                $description = sprintf('%s%03d-%d', self::CONSUMPTION_PREFIX, $operationNumber, $lineNumber);
                $matches = OperationMaterial::query()->where('operation_id', $operation->id)
                    ->where('material_id', $material->id)->get();

                if ($matches->count() > 1) {
                    throw new RuntimeException("Duplicate material rows already exist for [{$description}]; no rows were altered.");
                }

                if ($matches->isNotEmpty()) {
                    $existing = $matches->first();
                    if ((float) $existing->qty !== (float) $quantity
                        || (float) $existing->price !== 0.0
                        || (float) $existing->total !== 0.0
                        || $existing->description !== $description) {
                        throw new RuntimeException("Existing material row [{$description}] differs from the expected neutral demo row; it was not rewritten.");
                    }
                    continue;
                }

                OperationMaterial::query()->create([
                    'operation_id' => $operation->id,
                    'material_id' => $material->id,
                    'qty' => $quantity,
                    // Required legacy columns use neutral zeroes; no multiplication is performed.
                    'price' => 0,
                    'total' => 0,
                    'description' => $description,
                ]);
            }
        }
    }

    private function ensureSupervisorHistory(array $operations, array $supervisors): void
    {
        /** @var WorkflowService $workflow */
        $workflow = app(WorkflowService::class);

        foreach (range(1, 20) as $index) {
            $operation = $operations[$index];
            $supervisor = $supervisors[($index - 1) % count($supervisors)];
            $desiredStatus = match (true) {
                $index <= 8 => 'pending',
                $index <= 16 => 'approved',
                default => 'need_revision',
            };
            $decisionComment = $desiredStatus === 'need_revision'
                ? sprintf('DEMO-MAINT-RETURN-%03d: synthetic correction requested for local testing.', $index)
                : null;
            $pivot = DB::table('operation_supervisors')->where('operation_id', $operation->id)
                ->where('user_id', $supervisor->id)->first();
            $requestTransfer = WorkflowTransfer::query()
                ->where('operation_id', $operation->id)
                ->where('user_id_to', $supervisor->id)
                ->where('type', WorkflowTransfer::TYPE_LEAK_CHECK_REQUEST)
                ->get();

            if ($pivot) {
                if ($pivot->status !== $desiredStatus
                    || ($desiredStatus === 'approved' && $pivot->approved_at === null)
                    || ($desiredStatus !== 'approved' && $pivot->approved_at !== null)
                    || ($desiredStatus === 'need_revision' && $pivot->comment !== $decisionComment)
                    || $requestTransfer->count() !== 1
                    || ($desiredStatus === 'pending' && $requestTransfer->first()->receive_date !== null)
                    || ($desiredStatus !== 'pending' && $requestTransfer->first()->receive_date === null)) {
                    throw new RuntimeException("Existing supervisor history for demo operation {$index} is inconsistent; it was not modified.");
                }
                $rejectionCount = WorkflowTransfer::query()
                    ->where('operation_id', $operation->id)
                    ->where('user_id_to', $operation->user_id)
                    ->where('type', WorkflowTransfer::TYPE_LEAK_CHECK_REJECTED)
                    ->count();
                if ($rejectionCount !== ($desiredStatus === 'need_revision' ? 1 : 0)) {
                    throw new RuntimeException("Existing returned-workflow history for demo operation {$index} is inconsistent; it was not modified.");
                }
                continue;
            }

            if ($requestTransfer->isNotEmpty()) {
                throw new RuntimeException("Workflow transfer exists without its supervisor row for demo operation {$index}; no history was altered.");
            }

            OperationSupervisor::query()->create([
                'operation_id' => $operation->id,
                'user_id' => $supervisor->id,
                'status' => 'pending',
                'comment' => sprintf('DEMO-MAINT-REVIEW-%03d: synthetic supervisor review.', $index),
                'approved_at' => null,
                'order' => 1,
            ]);
            WorkflowTransfer::query()->create([
                'user_id_from' => $operation->user_id,
                'user_id_to' => $supervisor->id,
                'riser_id' => $operation->riser_id,
                'operation_id' => $operation->id,
                'type' => WorkflowTransfer::TYPE_LEAK_CHECK_REQUEST,
                'title' => 'DEMO-MAINT synthetic supervisor review',
                'comment' => sprintf('DEMO-MAINT-REVIEW-%03d: synthetic supervisor review.', $index),
                'send_date' => now(),
            ]);

            if ($desiredStatus === 'pending') {
                continue;
            }

            $approved = $desiredStatus === 'approved';
            $operation->supervisors()->updateExistingPivot($supervisor->id, [
                'status' => $desiredStatus,
                'comment' => $decisionComment,
                'approved_at' => $approved ? now() : null,
            ]);

            $transfer = WorkflowTransfer::query()
                ->where('operation_id', $operation->id)
                ->where('user_id_to', $supervisor->id)
                ->where('type', WorkflowTransfer::TYPE_LEAK_CHECK_REQUEST)
                ->pending()
                ->firstOrFail();
            $workflow->resolveLeakCheck($transfer, $supervisor, $approved, $decisionComment);
        }
    }

    private function assertFinalData(int $zoneId, array $risers): void
    {
        $expectedCounts = [
            'material_categories' => 6,
            'materials' => 30,
            'operation_categories' => 8,
            'users' => 5,
            'operations' => 40,
            'material_lines' => 100,
            'supervisor_rows' => 20,
        ];

        $actual = [
            'material_categories' => DB::table('material_categories')->where('code', 'like', self::CATEGORY_PREFIX.'%')->count(),
            'materials' => DB::table('materials')->where('code', 'like', self::MATERIAL_PREFIX.'%')->count(),
            'operation_categories' => DB::table('operation_categories')->where('code', 'like', self::OPERATION_CATEGORY_PREFIX.'%')->count(),
            'users' => DB::table('users')->where('username', 'like', self::USER_PREFIX.'%')->count(),
            'operations' => DB::table('operations')->where('description', 'like', self::OPERATION_PREFIX.'%')->count(),
            'material_lines' => DB::table('operation_materials')->where('description', 'like', self::CONSUMPTION_PREFIX.'%')->count(),
            'supervisor_rows' => DB::table('operation_supervisors')
                ->join('operations', 'operations.id', '=', 'operation_supervisors.operation_id')
                ->where('operations.description', 'like', self::OPERATION_PREFIX.'%')->count(),
        ];

        foreach ($expectedCounts as $key => $expected) {
            if ($actual[$key] !== $expected) {
                throw new RuntimeException("Final verification failed for {$key}: expected {$expected}, found {$actual[$key]}; transaction rolled back.");
            }
        }

        $badForeignKeys = DB::table('operations')
            ->where('description', 'like', self::OPERATION_PREFIX.'%')
            ->where(function ($query) {
                $query->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('riser')->whereColumn('riser.id', 'operations.riser_id'))
                    ->orWhereNotExists(fn ($sub) => $sub->selectRaw('1')->from('users')->whereColumn('users.id', 'operations.user_id'))
                    ->orWhereNotExists(fn ($sub) => $sub->selectRaw('1')->from('operation_categories')->whereColumn('operation_categories.id', 'operations.operation_category_id'));
            })->count();
        $badMaterials = DB::table('operation_materials')->where('description', 'like', self::CONSUMPTION_PREFIX.'%')
            ->where(function ($query) {
                $query->where('qty', '<=', 0)->orWhere('price', '<>', 0)->orWhere('total', '<>', 0)
                    ->orWhereNotExists(fn ($sub) => $sub->selectRaw('1')->from('operations')->whereColumn('operations.id', 'operation_materials.operation_id'))
                    ->orWhereNotExists(fn ($sub) => $sub->selectRaw('1')->from('materials')->whereColumn('materials.id', 'operation_materials.material_id'));
            })->count();

        if ($badForeignKeys !== 0 || $badMaterials !== 0) {
            throw new RuntimeException('Final foreign-key, quantity, or neutral-material-value verification failed; transaction rolled back.');
        }

        $operationStatus = DB::table('operations')->where('description', 'like', self::OPERATION_PREFIX.'%')
            ->select('status')->selectRaw('count(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        if ((int) ($operationStatus['submitOperation'] ?? 0) !== 8
            || (int) ($operationStatus['approved'] ?? 0) !== 8
            || (int) ($operationStatus['need_revision'] ?? 0) !== 4
            || (int) ($operationStatus['0'] ?? 0) !== 20) {
            throw new RuntimeException('Synthetic operation statuses do not match the declared status plan; transaction rolled back.');
        }

        $supervisorStatus = DB::table('operation_supervisors')
            ->join('operations', 'operations.id', '=', 'operation_supervisors.operation_id')
            ->where('operations.description', 'like', self::OPERATION_PREFIX.'%')
            ->select('operation_supervisors.status')->selectRaw('count(*) AS aggregate')
            ->groupBy('operation_supervisors.status')->pluck('aggregate', 'status');
        if ((int) ($supervisorStatus['pending'] ?? 0) !== 8
            || (int) ($supervisorStatus['approved'] ?? 0) !== 8
            || (int) ($supervisorStatus['need_revision'] ?? 0) !== 4) {
            throw new RuntimeException('Synthetic supervisor status totals are inconsistent; transaction rolled back.');
        }

        $unrepairedRiserCount = DB::table('riser')->whereIn('r_giscode', collect($risers)->pluck('r_giscode'))
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('operations')->whereColumn('operations.riser_id', 'riser.id')
                    ->where('operations.description', 'like', self::OPERATION_PREFIX.'%');
            })->count();
        $outsideDemoZone = DB::table('riser')->whereIn('r_giscode', collect($risers)->pluck('r_giscode'))
            ->whereRaw('NOT ST_Covers((SELECT geom FROM zone WHERE id = ?), riser.geom)', [$zoneId])->count();

        if ($unrepairedRiserCount !== 1 || $outsideDemoZone !== 0) {
            throw new RuntimeException('Riser repair distribution or Zone spatial boundary check failed; transaction rolled back.');
        }

        $duplicateUsage = DB::table('operation_materials')->where('description', 'like', self::CONSUMPTION_PREFIX.'%')
            ->select('operation_id', 'material_id')->groupBy('operation_id', 'material_id')->havingRaw('count(*) > 1')->count();
        if ($duplicateUsage !== 0) {
            throw new RuntimeException('A synthetic operation repeats a material; transaction rolled back.');
        }
    }

    private function summary(int $zoneId): array
    {
        return [
            'zone_id' => $zoneId,
            'demo_risers' => DB::table('riser')->where('r_giscode', 'like', self::RISER_PREFIX.'%')->count(),
            'material_categories' => DB::table('material_categories')->where('code', 'like', self::CATEGORY_PREFIX.'%')->count(),
            'materials' => DB::table('materials')->where('code', 'like', self::MATERIAL_PREFIX.'%')->count(),
            'operation_categories' => DB::table('operation_categories')->where('code', 'like', self::OPERATION_CATEGORY_PREFIX.'%')->count(),
            'users' => DB::table('users')->where('username', 'like', self::USER_PREFIX.'%')->count(),
            'operations' => DB::table('operations')->where('description', 'like', self::OPERATION_PREFIX.'%')->count(),
            'operation_statuses' => DB::table('operations')->where('description', 'like', self::OPERATION_PREFIX.'%')
                ->select('status')->selectRaw('count(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status')->all(),
            'material_lines' => DB::table('operation_materials')->where('description', 'like', self::CONSUMPTION_PREFIX.'%')->count(),
            'supervisor_rows' => DB::table('operation_supervisors')
                ->join('operations', 'operations.id', '=', 'operation_supervisors.operation_id')
                ->where('operations.description', 'like', self::OPERATION_PREFIX.'%')->count(),
            'supervisor_statuses' => DB::table('operation_supervisors')
                ->join('operations', 'operations.id', '=', 'operation_supervisors.operation_id')
                ->where('operations.description', 'like', self::OPERATION_PREFIX.'%')
                ->select('operation_supervisors.status')->selectRaw('count(*) AS aggregate')
                ->groupBy('operation_supervisors.status')->pluck('aggregate', 'status')->all(),
            'stored_total_cost_sum' => DB::table('operations')->where('description', 'like', self::OPERATION_PREFIX.'%')->sum('total_cost'),
        ];
    }

    private function superadminSnapshot(): array
    {
        return DB::table('users')
            ->join('model_has_roles', function ($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id')
                    ->where('model_has_roles.model_type', User::class);
            })
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', 'superadmin')->where('roles.guard_name', 'web')
            ->orderBy('users.id')
            ->get(['users.id', 'users.name', 'users.username', 'users.email', 'users.password', 'users.role_id', 'users.zone_id', 'users.updated_at'])
            ->map(fn ($user) => (array) $user)
            ->toArray();
    }
}
