<?php

namespace Tests\Feature;

use App\Models\GroupUser;
use App\Models\MaintenanceCase;
use App\Models\Material;
use App\Models\Operation;
use App\Models\OperationCategory;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class ReportExcelBossExportTest extends TestCase
{
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $identity = DB::selectOne('SELECT current_database() AS database_name, host(inet_server_addr()) AS server_host, inet_server_port() AS server_port, system_identifier::text AS cluster_id FROM pg_control_system()');
        } catch (\Throwable $exception) {
            $this->markTestSkipped('The isolated PostgreSQL/PostGIS test database is unavailable.');
        }
        self::assertSame('testing', app()->environment());
        self::assertSame('pgsql', config('database.default'));
        self::assertSame('alamaktest', $identity->database_name);
        self::assertSame('127.0.0.1', $identity->server_host);
        self::assertSame(55432, (int) $identity->server_port);
        self::assertSame('7703134720547896280', $identity->cluster_id);
        self::assertTrue(DB::selectOne("select exists(select 1 from pg_extension where extname = 'postgis') as installed")->installed);
        DB::beginTransaction();
        $this->transactionStarted = true;
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted && DB::transactionLevel() > 0) DB::rollBack();
        parent::tearDown();
    }

    public function test_boss_excel_download_has_eight_scoped_sheets_and_materials(): void
    {
        [$boss, $allowedOperation, $allowedCode, $forbiddenCode] = $this->fixtures();

        $response = $this->actingAs($boss)->get(route('report.excel', ['dataset' => 'operations']));
        $response->assertOk();
        self::assertStringContainsString('spreadsheetml.sheet', (string) $response->headers->get('content-type'));

        $path = tempnam(sys_get_temp_dir(), 'boss-report-');
        try {
            file_put_contents($path, $response->streamedContent());
            $zip = new ZipArchive();
            self::assertTrue($zip->open($path));
            self::assertNotFalse($zip->locateName('xl/workbook.xml'));
            self::assertNotFalse($zip->locateName('xl/worksheets/sheet8.xml'));
            $zip->close();

            $workbook = IOFactory::load($path);
            self::assertCount(8, $workbook->getAllSheets());
            self::assertSame('داشبورد', $workbook->getSheet(0)->getTitle());
            self::assertSame('گردش‌کار', $workbook->getSheet(6)->getTitle());
            $codes = [];
            foreach ($workbook->getSheet(1)->toArray() as $row) $codes = array_merge($codes, array_map('strval', $row));
            self::assertContains($allowedCode, $codes);
            self::assertNotContains($forbiddenCode, $codes);
            self::assertSame((string) $allowedOperation->id, (string) $workbook->getSheet(3)->getCell('A2')->getValue());
            self::assertSame('12345', (string) $workbook->getSheet(4)->getCell('B2')->getValue());
            self::assertSame('2.75', (string) $workbook->getSheet(4)->getCell('E2')->getValue());
            self::assertSame('2.75', (string) $workbook->getSheet(5)->getCell('D2')->getValue());
        } finally {
            @unlink($path);
        }
    }

    public function test_empty_filtered_report_returns_without_creating_xlsx(): void
    {
        [$boss] = $this->fixtures();
        $this->actingAs($boss)->get(route('report.excel', ['dataset' => 'operations', 'status' => 'no-match']))
            ->assertRedirect()->assertSessionHas('error');
    }

    private function fixtures(): array
    {
        $suffix = bin2hex(random_bytes(5));
        $organization = Organization::query()->create(['code' => 'excel-'.$suffix, 'name' => 'Excel Fixture', 'status' => 'active']);
        $zone = DB::selectOne("INSERT INTO zone (name, organization_id, geom, created_at, updated_at) VALUES (?, ?, ST_MakeEnvelope(49,34,50,35,4326), now(), now()) RETURNING id", ['Excel zone '.$suffix, $organization->id]);
        $allowedGroup = GroupUser::query()->create(['name' => 'Boss allowed '.$suffix, 'organization_id' => $organization->id, 'zone_id' => $zone->id]);
        $otherGroup = GroupUser::query()->create(['name' => 'Other group '.$suffix, 'organization_id' => $organization->id, 'zone_id' => $zone->id]);
        $role = Role::findOrCreate('boss', 'web');
        $role->givePermissionTo(Permission::findOrCreate('report.view', 'web'), Permission::findOrCreate('report.export', 'web'));
        $boss = User::query()->create([
            'name' => 'Fixture Boss', 'username' => 'excel-boss-'.$suffix, 'email' => 'excel-'.$suffix.'@example.test',
            'password' => 'FixturePass123', 'organization_id' => $organization->id, 'zone_id' => $zone->id,
            'group_user_id' => $allowedGroup->id,
        ]);
        $boss->assignRole($role);

        $riser = fn (string $code): int => (int) DB::selectOne(
            'INSERT INTO riser (r_giscode, giscode, status, geom, created_at, updated_at) VALUES (?, ?, ?, ST_SetSRID(ST_Point(49.5,34.5),4326), now(), now()) RETURNING id',
            [$code, $code, 'active']
        )->id;
        $allowedRiserId = $riser('GIS-'.$suffix);
        $otherRiserId = $riser('HIDDEN-'.$suffix);
        $category = OperationCategory::query()->create(['title' => 'Excel fixture operation', 'code' => 'excel-op-'.$suffix, 'is_active' => true]);
        $case = MaintenanceCase::query()->create([
            'riser_id' => $allowedRiserId, 'reported_by' => $boss->id, 'assigned_group_id' => $allowedGroup->id,
            'status' => 'open', 'priority' => 'normal', 'identified_at' => now(),
        ]);
        $allowed = Operation::query()->create([
            'riser_id' => $allowedRiserId, 'maintenance_case_id' => $case->id, 'group_user_id' => $allowedGroup->id,
            'operation_category_id' => $category->id, 'user_id' => $boss->id, 'operation_date' => now()->toDateString(),
            'status' => 'approved', 'priority' => 'normal', 'total_cost' => 0, 'description' => 'fixture only',
        ]);
        Operation::query()->create([
            'riser_id' => $otherRiserId, 'group_user_id' => $otherGroup->id, 'operation_category_id' => $category->id,
            'user_id' => $boss->id, 'operation_date' => now()->toDateString(), 'status' => 'approved',
            'priority' => 'normal', 'total_cost' => 0, 'description' => 'must not export',
        ]);

        $materialCategoryId = DB::table('material_categories')->insertGetId([
            'title' => 'Excel fixture materials', 'code' => 'excel-mcat-'.$suffix, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $material = Material::query()->create([
            'material_category_id' => $materialCategoryId, 'title' => 'Excel fixture material',
            'code' => '12345', 'unit' => 'kg', 'default_price' => 1, 'is_active' => true,
        ]);
        DB::table('operation_materials')->insert([
            'operation_id' => $allowed->id, 'material_id' => $material->id, 'qty' => '2.75', 'price' => '1.00', 'total' => '2.75',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$boss, $allowed, 'GIS-'.$suffix, 'HIDDEN-'.$suffix];
    }
}
