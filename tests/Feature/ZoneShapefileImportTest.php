<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\ZoneShapefileImportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class ZoneShapefileImportTest extends TestCase
{
    private bool $transactionStarted = false;
    private ?array $artifact = null;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $identity = DB::selectOne('SELECT current_database() AS database_name, host(inet_server_addr()) AS server_host, inet_server_port() AS server_port, system_identifier::text AS cluster_id FROM pg_control_system()');
        } catch (\Throwable) {
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
        if ($this->artifact) File::deleteDirectory($this->artifact['root']);
        if ($this->transactionStarted && DB::transactionLevel() > 0) DB::rollBack();
        parent::tearDown();
    }

    public function test_preview_does_not_write_and_explicit_confirm_creates_wgs84_zone(): void
    {
        [$user, $organization] = $this->importUser();
        $this->actingAs($user);
        $service = app(ZoneShapefileImportService::class);
        $uploaded = $service->upload($this->uploadedFixture('ناحیه آزمایشی'), $user);
        $before = DB::table('zone')->count();

        $preview = $service->preview($uploaded['meta'], $user, ['name_field' => 'ZONE_NAME', 'organization_id' => $organization->id, 'encoding' => 'UTF-8']);
        self::assertSame(1, $preview['valid_count']);
        self::assertSame(0, $preview['invalid_count']);
        self::assertSame($before, DB::table('zone')->count(), 'Preview must never write the target zone table.');

        $result = $service->import($uploaded['meta'], $preview['mapping'], $user, 'skip', false);
        self::assertSame(['created' => 1, 'updated' => 0, 'skipped' => 0, 'failed' => 0], $result);
        $zone = DB::table('zone')->where('organization_id', $organization->id)->where('name', 'ناحیه آزمایشی')->first();
        self::assertNotNull($zone);
        self::assertSame(4326, (int) DB::scalar('SELECT ST_SRID(geom) FROM zone WHERE id = ?', [$zone->id]));
        self::assertSame('MULTIPOLYGON', strtoupper(DB::scalar('SELECT GeometryType(geom) FROM zone WHERE id = ?', [$zone->id])));
    }

    public function test_default_duplicate_policy_skips_existing_zone_without_overwrite(): void
    {
        [$user, $organization] = $this->importUser();
        $this->actingAs($user);
        $service = app(ZoneShapefileImportService::class);
        $first = $service->upload($this->uploadedFixture('زون تکراری'), $user);
        $mapping = ['name_field' => 'ZONE_NAME', 'organization_id' => $organization->id, 'encoding' => 'UTF-8'];
        $preview = $service->preview($first['meta'], $user, $mapping);
        $service->import($first['meta'], $preview['mapping'], $user, 'skip', false);
        $before = DB::scalar("SELECT encode(ST_AsEWKB(geom), 'hex') FROM zone WHERE organization_id = ? AND name = ?", [$organization->id, 'زون تکراری']);

        $second = $service->upload($this->uploadedFixture('زون تکراری', true), $user);
        $secondPreview = $service->preview($second['meta'], $user, $mapping);
        self::assertSame(1, $secondPreview['existing_duplicate_count']);
        $result = $service->import($second['meta'], $secondPreview['mapping'], $user, 'skip', false);
        self::assertSame(['created' => 0, 'updated' => 0, 'skipped' => 1, 'failed' => 0], $result);
        self::assertSame($before, DB::scalar("SELECT encode(ST_AsEWKB(geom), 'hex') FROM zone WHERE organization_id = ? AND name = ?", [$organization->id, 'زون تکراری']));
    }

    private function importUser(): array
    {
        $suffix = bin2hex(random_bytes(5));
        $organization = Organization::query()->create(['code' => 'shape-'.$suffix, 'name' => 'Shapefile Test Org', 'status' => 'active']);
        $role = Role::findOrCreate('superadmin', 'web');
        $user = User::query()->create([
            'name' => 'Shapefile Test Admin', 'username' => 'shape-admin-'.$suffix, 'email' => 'shape-'.$suffix.'@example.test',
            'password' => 'FixturePass123', 'organization_id' => $organization->id,
        ]);
        $user->assignRole($role);
        return [$user, $organization];
    }

    private function uploadedFixture(string $name, bool $differentGeometry = false): \Illuminate\Http\UploadedFile
    {
        $ogr2ogr = (string) config('shapefile.ogr2ogr', 'ogr2ogr');
        $ogrinfo = (string) config('shapefile.ogrinfo', 'ogrinfo');
        foreach ([$ogr2ogr, $ogrinfo] as $binary) {
            try {
                $probe = new Process([$binary, '--version']);
                $probe->setTimeout(10);
                $probe->run();
            } catch (\Throwable) {
                $this->markTestSkipped('GDAL/OGR configured by the test environment is required.');
            }
            if (! $probe->isSuccessful()) $this->markTestSkipped('GDAL/OGR configured by the test environment is required.');
        }
        config(['shapefile.ogr2ogr' => $ogr2ogr, 'shapefile.ogrinfo' => $ogrinfo]);
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zone-shape-feature-'.bin2hex(random_bytes(5));
        File::ensureDirectoryExists($root);
        $this->artifact = ['root' => $root];
        $start = $differentGeometry ? 49.7 : 49.5;
        $end = $differentGeometry ? 49.8 : 49.6;
        file_put_contents($root.'/input.geojson', json_encode([
            'type' => 'FeatureCollection', 'name' => 'zones',
            'crs' => ['type' => 'name', 'properties' => ['name' => 'urn:ogc:def:crs:EPSG::4326']],
            'features' => [[
                'type' => 'Feature', 'properties' => ['ZONE_NAME' => $name],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[$start,34.5],[$end,34.5],[$end,34.6],[$start,34.6],[$start,34.5]]]],
            ]],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $gdalEnv = array_filter([
            'GDAL_DATA' => config('shapefile.gdal_data'),
            'PROJ_DATA' => config('shapefile.proj_data'),
            'PROJ_LIB' => config('shapefile.proj_data'),
        ], static fn ($value) => is_string($value) && $value !== '');
        (new Process([$ogr2ogr, '-f', 'ESRI Shapefile', $root.'/zones.shp', $root.'/input.geojson', '-t_srs', 'EPSG:4326', '-nlt', 'PROMOTE_TO_MULTI', '-lco', 'ENCODING=UTF-8'], null, $gdalEnv))->mustRun();
        file_put_contents($root.'/zones.cpg', 'UTF-8');
        $zipPath = $root.'/zones.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach (['shp','shx','dbf','prj','cpg'] as $extension) $zip->addFile($root.'/zones.'.$extension, 'zones.'.$extension);
        $zip->close();
        return new \Illuminate\Http\UploadedFile($zipPath, 'zones.zip', 'application/zip', null, true);
    }
}
