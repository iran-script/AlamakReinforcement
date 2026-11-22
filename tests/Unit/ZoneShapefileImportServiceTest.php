<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ZoneShapefileImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class ZoneShapefileImportServiceTest extends TestCase
{
    public function test_gdal_inspects_valid_polygon_shapefile_without_database_access(): void
    {
        $ogr2ogr = (string) config('shapefile.ogr2ogr', 'ogr2ogr');
        $ogrinfo = (string) config('shapefile.ogrinfo', 'ogrinfo');
        if (! class_exists(ZipArchive::class)) $this->markTestSkipped('GDAL/OGR and ZipArchive are required for this fixture test.');
        config(['shapefile.ogr2ogr' => $ogr2ogr, 'shapefile.ogrinfo' => $ogrinfo]);

        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zone-shape-test-'.bin2hex(random_bytes(5));
        File::ensureDirectoryExists($root);
        $token = null;
        $user = new User();
        $user->id = 987654321;
        try {
            file_put_contents($root.'/input.geojson', json_encode([
                'type' => 'FeatureCollection', 'name' => 'zones',
                'crs' => ['type' => 'name', 'properties' => ['name' => 'urn:ogc:def:crs:EPSG::4326']],
                'features' => [[
                    'type' => 'Feature', 'properties' => ['ZONE_NAME' => 'ناحیه نمونه', 'CODE' => '0012'],
                    'geometry' => ['type' => 'Polygon', 'coordinates' => [[[49.5,34.5],[49.6,34.5],[49.6,34.6],[49.5,34.6],[49.5,34.5]]]],
                ]],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $create = new Process([$ogr2ogr, '-f', 'ESRI Shapefile', $root.'/zones.shp', $root.'/input.geojson', '-t_srs', 'EPSG:32639', '-nlt', 'PROMOTE_TO_MULTI', '-lco', 'ENCODING=UTF-8']);
            $create->setTimeout(30);
            $create->mustRun();
            file_put_contents($root.'/zones.cpg', 'UTF-8');

            $zipPath = $root.'/zones.zip';
            $zip = new ZipArchive();
            self::assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
            foreach (['shp','shx','dbf','prj','cpg'] as $extension) $zip->addFile($root.'/zones.'.$extension, 'zones.'.$extension);
            $zip->close();

            $service = app(ZoneShapefileImportService::class);
            $uploaded = new UploadedFile($zipPath, 'zones.zip', 'application/zip', null, true);
            $result = $service->upload($uploaded, $user);
            $token = $result['token'];
            self::assertSame('Polygon', $result['meta']['geometry']);
            self::assertSame(1, $result['meta']['feature_count']);
            self::assertSame('EPSG:32639', $result['meta']['crs']);
            self::assertSame('UTF-8', $result['meta']['encoding_hint']);
            $detectedFields = array_column($result['meta']['fields'], 'name');
            self::assertContains('ZONE_NAME', $detectedFields);
            self::assertContains('CODE', $detectedFields);

            $shp = storage_path('app/private/zone-imports/'.$user->id.'/'.$token.'/source/zones.shp');
            $normalized = $root.'/normalized.geojsonl';
            $transform = new \ReflectionMethod($service, 'transformToWgs84');
            $transform->invoke($service, $shp, $normalized, 'UTF-8');
            $feature = json_decode(trim((string) file_get_contents($normalized)), true, 128, JSON_THROW_ON_ERROR);
            self::assertSame('MultiPolygon', $feature['geometry']['type']);
            $ring = $feature['geometry']['coordinates'][0][0];
            $longitudes = array_column($ring, 0);
            $latitudes = array_column($ring, 1);
            self::assertGreaterThan(49.4, min($longitudes));
            self::assertLessThan(49.7, max($longitudes));
            self::assertGreaterThan(34.4, min($latitudes));
            self::assertLessThan(34.7, max($latitudes));
            self::assertSame('ناحیه نمونه', $feature['properties']['ZONE_NAME']);
        } finally {
            if ($token) $service->delete(['token' => $token, 'user_id' => $user->id], $user);
            File::deleteDirectory($root);
        }
    }

    public function test_archive_paths_with_directory_traversal_are_rejected(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zone-zip-test-'.bin2hex(random_bytes(5));
        File::ensureDirectoryExists($root);
        try {
            $zipPath = $root.'/unsafe.zip';
            $zip = new ZipArchive();
            self::assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
            $zip->addFromString('../zones.shp', 'not a shapefile');
            $zip->addFromString('../zones.shx', 'x');
            $zip->addFromString('../zones.dbf', 'x');
            $zip->addFromString('../zones.prj', 'x');
            $zip->close();

            $user = new User();
            $user->id = 987654322;
            try {
                app(ZoneShapefileImportService::class)->upload(new UploadedFile($zipPath, 'unsafe.zip', 'application/zip', null, true), $user);
                self::fail('Unsafe ZIP member paths should be rejected.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                self::assertArrayHasKey('file', $exception->errors());
            }
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_highly_compressed_archive_member_is_rejected(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zone-zip-bomb-test-'.bin2hex(random_bytes(5));
        File::ensureDirectoryExists($root);
        try {
            $zipPath = $root.'/compressed.zip';
            $zip = new ZipArchive();
            self::assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
            $zip->addFromString('zones.shp', str_repeat('A', 2 * 1024 * 1024));
            $zip->addFromString('zones.shx', 'x');
            $zip->addFromString('zones.dbf', 'x');
            $zip->addFromString('zones.prj', 'x');
            $zip->close();

            $user = new User();
            $user->id = 987654323;
            try {
                app(ZoneShapefileImportService::class)->upload(new UploadedFile($zipPath, 'compressed.zip', 'application/zip', null, true), $user);
                self::fail('Highly compressed ZIP members should be rejected.');
            } catch (\Illuminate\Validation\ValidationException $exception) {
                self::assertArrayHasKey('file', $exception->errors());
            }
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_missing_gdal_data_returns_the_wizard_configuration_error(): void
    {
        $original = config('shapefile.gdal_data');
        config(['shapefile.gdal_data' => sys_get_temp_dir().DIRECTORY_SEPARATOR.'missing-gdal-data']);
        try {
            $service = new ZoneShapefileImportService();
            $transform = new \ReflectionMethod($service, 'transformToWgs84');
            $transform->invoke($service, 'missing.shp', 'missing.geojsonl', 'UTF-8');
            self::fail('Missing GDAL data must stop transformation.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            self::assertStringContainsString('GDAL/PROJ', $exception->errors()['file'][0]);
        } finally {
            config(['shapefile.gdal_data' => $original]);
        }
    }
}
