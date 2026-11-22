<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Models\Zone;
use App\Services\ZoneBoundaryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use ZipArchive;

/** Handles private, short-lived Shapefile artifacts and their import lifecycle. */
class ZoneShapefileImportService
{
    public const MAX_UPLOAD_BYTES = 25 * 1024 * 1024;
    public const MAX_UNPACKED_BYTES = 100 * 1024 * 1024;
    public const MAX_FEATURES = 2000;
    public const PREVIEW_FEATURES = 100;
    public const TOKEN_TTL = 7200;

    private const ENCODINGS = ['UTF-8', 'CP1256', 'CP1252', 'ISO-8859-6'];

    private array $runtimeEnvironments = [];

    public function organizationsFor(User $user)
    {
        if ($user->isSuperAdmin()) return Organization::query()->orderBy('name')->get(['id', 'name']);
        if (! $user->organization_id) return collect();
        return Organization::query()->whereKey($user->organization_id)->get(['id', 'name']);
    }

    /** @return array{token:string,meta:array<string,mixed>} */
    public function upload(UploadedFile $file, User $user): array
    {
        $this->cleanupExpired();
        if (! $file->isValid() || $file->getSize() > self::MAX_UPLOAD_BYTES) {
            throw ValidationException::withMessages(['file' => 'فایل ZIP معتبر نیست یا از سقف ۲۵ مگابایت بیشتر است.']);
        }

        $token = Str::random(64);
        $folder = $this->folder($user, $token);
        File::ensureDirectoryExists($folder, 0700, true);
        $zipPath = $folder.DIRECTORY_SEPARATOR.'source.zip';
        if (! copy($file->getRealPath(), $zipPath)) {
            File::deleteDirectory($folder);
            throw ValidationException::withMessages(['file' => 'ذخیره امن فایل موقت انجام نشد.']);
        }

        try {
            $work = $folder.DIRECTORY_SEPARATOR.'source';
            File::ensureDirectoryExists($work, 0700, true);
            $parts = $this->extractValidatedArchive($zipPath, $work);
            $meta = $this->inspect($parts['shp']);
            $meta['encoding_hint'] = $this->encodingHint($parts['cpg'] ?? null);
            $meta['files'] = array_values(array_map('basename', $parts));
            $meta['sha256'] = hash_file('sha256', $zipPath);
            $meta['token'] = $token;
            $meta['user_id'] = $user->id;
            $meta['created_at'] = time();
            $meta['status'] = 'uploaded';
            $meta['relative_folder'] = $this->relativeFolder($user, $token);

            return ['token' => $token, 'meta' => $meta];
        } catch (\Throwable $exception) {
            File::deleteDirectory($folder);
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    public function preview(array $meta, User $user, array $mapping): array
    {
        $this->assertOwnerAndExpiry($meta, $user);
        $mapping = $this->validateMapping($meta, $user, $mapping);
        $folder = $this->folder($user, $meta['token']);
        $work = $folder.DIRECTORY_SEPARATOR.'preview-'.Str::random(8);
        File::ensureDirectoryExists($work, 0700, true);

        try {
            $shapefile = $this->revalidateAndExtract($meta, $user, $work);
            $normalized = $work.DIRECTORY_SEPARATOR.'normalized.geojsonl';
            $this->transformToWgs84($shapefile['shp'], $normalized, $mapping['encoding']);
            $summary = $this->scan($normalized, (int) $mapping['organization_id'], $mapping, $user, true);
            $summary['source'] = [
                'geometry' => $meta['geometry'], 'crs' => $meta['crs'],
                'transformed_to' => 'EPSG:4326', 'feature_count' => $meta['feature_count'],
                'encoding' => $mapping['encoding'],
            ];
            $previewPath = $folder.DIRECTORY_SEPARATOR.'preview.geojson';
            file_put_contents($previewPath, json_encode($summary['geojson'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), LOCK_EX);
            unset($summary['geojson']);
            return $summary + ['mapping' => $mapping, 'status' => 'previewed'];
        } finally {
            File::deleteDirectory($work);
        }
    }

    /** @return array{created:int,updated:int,skipped:int,failed:int} */
    public function import(array $meta, array $savedMapping, User $user, string $duplicateMode, bool $skipInvalid): array
    {
        $this->assertOwnerAndExpiry($meta, $user);
        $mapping = $this->validateMapping($meta, $user, $savedMapping);
        if (! in_array($duplicateMode, ['skip', 'reject', 'update'], true)) {
            throw ValidationException::withMessages(['duplicate_mode' => 'گزینه مدیریت موارد تکراری معتبر نیست.']);
        }

        $folder = $this->folder($user, $meta['token']);
        $work = $folder.DIRECTORY_SEPARATOR.'import-'.Str::random(8);
        File::ensureDirectoryExists($work, 0700, true);
        try {
            $shapefile = $this->revalidateAndExtract($meta, $user, $work);
            $normalized = $work.DIRECTORY_SEPARATOR.'normalized.geojsonl';
            $this->transformToWgs84($shapefile['shp'], $normalized, $mapping['encoding']);
            $scan = $this->scan($normalized, (int) $mapping['organization_id'], $mapping, $user, false);
            if ($scan['invalid_count'] > 0 && ! $skipInvalid) {
                throw ValidationException::withMessages(['skip_invalid' => 'هندسه یا نام نامعتبر وجود دارد؛ برای ورود فقط موارد معتبر، تأیید صریح لازم است.']);
            }
            if ($duplicateMode === 'reject' && ($scan['existing_duplicate_count'] + $scan['file_duplicate_count']) > 0) {
                throw ValidationException::withMessages(['duplicate_mode' => 'موارد تکراری با سازمان انتخاب‌شده وجود دارد؛ هیچ رکوردی ثبت نشد.']);
            }

            $result = DB::transaction(function () use ($normalized, $mapping, $user, $duplicateMode, $skipInvalid): array {
                DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['zone-shapefile-import:'.$mapping['organization_id']]);
                return $this->writeNormalized($normalized, $mapping, $user, $duplicateMode, $skipInvalid);
            }, 1);
            File::deleteDirectory($folder);
            return $result;
        } finally {
            if (is_dir($work)) File::deleteDirectory($work);
        }
    }

    public function previewResponsePath(array $meta, User $user): string
    {
        $this->assertOwnerAndExpiry($meta, $user);
        $path = $this->folder($user, $meta['token']).DIRECTORY_SEPARATOR.'preview.geojson';
        abort_unless(($meta['status'] ?? null) === 'previewed' && is_file($path), 404);
        return $path;
    }

    public function delete(array $meta, User $user): void
    {
        if (($meta['user_id'] ?? null) === $user->id && isset($meta['token'])) {
            File::deleteDirectory($this->folder($user, $meta['token']));
        }
    }

    public function cleanupExpired(): void
    {
        $root = storage_path('app/private/zone-imports');
        if (! is_dir($root)) return;
        foreach (File::directories($root) as $userDirectory) {
            foreach (File::directories($userDirectory) as $tokenDirectory) {
                if (filemtime($tokenDirectory) !== false && filemtime($tokenDirectory) < time() - self::TOKEN_TTL) {
                    File::deleteDirectory($tokenDirectory);
                }
            }
            if (File::isDirectory($userDirectory) && count(File::allFiles($userDirectory)) === 0) File::deleteDirectory($userDirectory);
        }
    }

    /** @return array{shp:string,shx:string,dbf:string,prj:string,cpg?:string} */
    private function extractValidatedArchive(string $zipPath, string $target): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true || $zip->numFiles < 4 || $zip->numFiles > 5) {
            throw ValidationException::withMessages(['file' => 'ZIP باید فقط اجزای SHP، SHX، DBF، PRJ و در صورت نیاز CPG را داشته باشد.']);
        }

        $entries = [];
        $baseNames = [];
        $totalSize = 0;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            $stat = $zip->statIndex($index);
            if (! is_string($name) || ! is_array($stat) || str_contains($name, '/') || str_contains($name, '\\')
                || str_contains($name, "\0") || $name === '.' || $name === '..' || preg_match('/^[A-Za-z]:/', $name)) {
                $zip->close();
                throw ValidationException::withMessages(['file' => 'مسیر داخلی ZIP مجاز نیست.']);
            }
            $opsys = 0; $attributes = 0;
            if ($zip->getExternalAttributesIndex($index, $opsys, $attributes) && $opsys === ZipArchive::OPSYS_UNIX) {
                $mode = ($attributes >> 16) & 0170000;
                if ($mode === 0120000) {
                    $zip->close();
                    throw ValidationException::withMessages(['file' => 'فایل پیوندی (Symlink) داخل ZIP پذیرفته نمی‌شود.']);
                }
            }
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $base = strtolower(pathinfo($name, PATHINFO_FILENAME));
            if (! in_array($extension, ['shp', 'shx', 'dbf', 'prj', 'cpg'], true) || $base === '' || isset($entries[$extension])) {
                $zip->close();
                throw ValidationException::withMessages(['file' => 'نام فایل‌ها یا اجزای Shapefile تکراری/نامعتبر است.']);
            }
            $size = (int) ($stat['size'] ?? 0);
            $compressed = (int) ($stat['comp_size'] ?? 0);
            $totalSize += $size;
            if ($size > 50 * 1024 * 1024 || $totalSize > self::MAX_UNPACKED_BYTES || ($size > 1024 * 1024 && $compressed > 0 && $size / $compressed > 250)) {
                $zip->close();
                throw ValidationException::withMessages(['file' => 'اندازه بازشده یا نسبت فشرده‌سازی ZIP از حد امن بیشتر است.']);
            }
            $entries[$extension] = $name;
            $baseNames[$base] = true;
        }
        if (count($baseNames) !== 1 || ! isset($entries['shp'], $entries['shx'], $entries['dbf'], $entries['prj'])) {
            $zip->close();
            throw ValidationException::withMessages(['file' => 'SHP، SHX، DBF و PRJ باید موجود و دارای نام پایه یکسان باشند.']);
        }
        foreach ($entries as $name) {
            if (! $zip->extractTo($target, [$name])) {
                $zip->close();
                throw ValidationException::withMessages(['file' => 'استخراج امن Shapefile انجام نشد.']);
            }
        }
        $zip->close();
        foreach ($entries as $extension => $name) $entries[$extension] = $target.DIRECTORY_SEPARATOR.$name;
        foreach (['shp', 'shx', 'dbf', 'prj'] as $required) {
            if (! is_file($entries[$required]) || filesize($entries[$required]) === 0) {
                throw ValidationException::withMessages(['file' => 'یکی از فایل‌های اصلی Shapefile خالی یا مفقود است.']);
            }
        }
        return $entries;
    }

    /** @return array{geometry:string,feature_count:int,fields:array<int,array{name:string,type:string}>,crs:string,crs_epsg:?string} */
    private function inspect(string $shp): array
    {
        $prj = preg_replace('/\.shp$/i', '.prj', $shp);
        if (! is_string($prj) || ! is_file($prj) || trim((string) file_get_contents($prj)) === '') {
            throw ValidationException::withMessages(['file' => 'فایل PRJ خالی یا مفقود است؛ CRS ورودی باید مشخص باشد.']);
        }
        $process = $this->gdalProcess('ogrinfo', ['-ro', '-so', '-al', '-fields=YES', '-geom=SUMMARY', $shp]);
        $process->setTimeout(30);
        $process->run();
        if (! $process->isSuccessful()) {
            $this->logGdalFailure('inspect', $process);
            throw ValidationException::withMessages(['file' => $this->gdalFailureMessage($process->getErrorOutput(), 'خواندن Shapefile')]);
        }
        $output = $process->getOutput();
        if (! preg_match('/^Geometry:\s*(.+)$/mi', $output, $geometryMatch)) {
            throw ValidationException::withMessages(['file' => 'نوع هندسه Shapefile قابل شناسایی نیست.']);
        }
        $geometry = trim($geometryMatch[1]);
        if (! preg_match('/^(Multi\s*)?Polygon$/i', $geometry)) {
            throw ValidationException::withMessages(['file' => 'فقط Polygon و MultiPolygon پشتیبانی می‌شوند؛ نوع موجود: '.$geometry]);
        }
        if (! preg_match('/^Feature Count:\s*(\d+)\s*$/mi', $output, $countMatch)) {
            throw ValidationException::withMessages(['file' => 'تعداد Featureهای Shapefile قابل خواندن نیست.']);
        }
        $count = (int) $countMatch[1];
        if ($count < 1 || $count > self::MAX_FEATURES) {
            throw ValidationException::withMessages(['file' => 'تعداد رکوردها باید بین ۱ تا '.self::MAX_FEATURES.' باشد؛ برای فایل بزرگ‌تر آن را به چند ZIP تقسیم کنید.']);
        }
        if (! preg_match('/Layer SRS WKT:\s*\R(.+?)(?:\R\s*Data axis to CRS axis mapping:|\R\s*FID:|$)/si', $output, $srsMatch)) {
            throw ValidationException::withMessages(['file' => 'GDAL نتوانست CRS موجود در PRJ را تفسیر کند.']);
        }
        $wkt = trim($srsMatch[1]);
        $epsg = null;
        if (preg_match_all('/(?:AUTHORITY|ID)\["EPSG"\s*,\s*"?(\d+)"?\]/i', $wkt, $authorityMatches) && ! empty($authorityMatches[1])) {
            $epsg = end($authorityMatches[1]);
        }
        preg_match_all('/^([A-Za-z][A-Za-z0-9_]*)\s*:\s*(Integer64|Integer|Real|String|DateTime|Date|Time)\b[^\r\n]*/mi', $output, $fieldMatches, PREG_SET_ORDER);
        $fields = [];
        foreach ($fieldMatches as $fieldMatch) {
            if (strcasecmp($fieldMatch[1], 'FID') !== 0) $fields[] = ['name' => $fieldMatch[1], 'type' => $fieldMatch[2]];
        }
        return ['geometry' => $geometry, 'feature_count' => $count, 'fields' => $fields,
            'crs' => $epsg ? 'EPSG:'.$epsg : 'CRS تعریف‌شده در WKT/PRJ', 'crs_epsg' => $epsg];
    }

    private function transformToWgs84(string $shp, string $destination, string $encoding): void
    {
        $process = $this->gdalProcess('ogr2ogr', [
            '-f', 'GeoJSONSeq', $destination, $shp,
            '-t_srs', 'EPSG:4326', '-nlt', 'PROMOTE_TO_MULTI', '-dim', 'XY', '-oo', 'ENCODING='.$encoding,
        ]);
        $process->setTimeout(180);
        $process->run();
        if (! $process->isSuccessful()) {
            $this->logGdalFailure('transform', $process);
            throw ValidationException::withMessages(['file' => $this->gdalFailureMessage($process->getErrorOutput(), 'تبدیل CRS به EPSG:4326')]);
        }
        if (! is_file($destination) || filesize($destination) > 160 * 1024 * 1024) {
            throw ValidationException::withMessages(['file' => 'خروجی تبدیل GDAL خالی یا بزرگ‌تر از سقف امن است.']);
        }
    }

    /** @return array<string,mixed> */
    private function scan(string $normalized, int $organizationId, array $mapping, User $user, bool $withGeoJson): array
    {
        $summary = ['feature_count' => 0, 'valid_count' => 0, 'invalid_count' => 0,
            'existing_duplicate_count' => 0, 'existing_linked_count' => 0, 'file_duplicate_count' => 0, 'issues' => [], 'samples' => [], 'geojson' => ['type' => 'FeatureCollection', 'features' => []]];
        $seen = [];
        foreach ($this->readBatches($normalized) as $batch) {
            $classified = $this->classifyBatch($batch, $organizationId, $mapping, $seen, $user);
            foreach ($classified as $item) {
                $summary['feature_count']++;
                if (! $item['valid']) {
                    $summary['invalid_count']++;
                    if (count($summary['issues']) < 30) $summary['issues'][] = ['row' => $item['row'], 'reason' => $item['reason']];
                    continue;
                }
                $summary['valid_count']++;
                if ($item['duplicate_kind'] === 'existing') {
                    $summary['existing_duplicate_count']++;
                    if ($item['existing']['linked'] ?? false) $summary['existing_linked_count']++;
                }
                if ($item['duplicate_kind'] === 'file') $summary['file_duplicate_count']++;
                if ($withGeoJson && count($summary['samples']) < 20) $summary['samples'][] = ['name' => $item['name'], 'source_values' => $item['properties']];
                if ($withGeoJson && count($summary['geojson']['features']) < self::PREVIEW_FEATURES) {
                    $summary['geojson']['features'][] = ['type' => 'Feature', 'geometry' => $item['geometry'], 'properties' => ['name' => $item['name']]];
                }
            }
        }
        if ($summary['feature_count'] < 1) throw ValidationException::withMessages(['file' => 'Shapefile هیچ Feature قابل خواندنی ندارد.']);
        return $summary;
    }

    /** @return array<int,array<string,mixed>> */
    private function classifyBatch(array $batch, int $organizationId, array $mapping, array &$seen, User $user): array
    {
        $items = [];
        $geometries = [];
        $names = [];
        foreach ($batch as $record) {
            $feature = $record['feature'];
            $props = $feature['properties'] ?? [];
            $rawName = is_array($props) ? ($props[$mapping['name_field']] ?? null) : null;
            $name = is_scalar($rawName) ? trim((string) $rawName) : '';
            $reason = null;
            if ($name === '') $reason = 'نام زون خالی است.';
            elseif (mb_strlen($name, 'UTF-8') > 150) $reason = 'نام زون از ۱۵۰ نویسه بیشتر است.';
            elseif (! is_array($feature['geometry'] ?? null)) $reason = 'هندسه وجود ندارد یا قابل خواندن نیست.';
            $items[$record['row']] = ['row' => $record['row'], 'name' => $name, 'geometry' => $feature['geometry'] ?? null,
                'properties' => is_array($props) ? $props : [],
                'geojson' => isset($feature['geometry']) ? json_encode($feature['geometry'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null,
                'valid' => $reason === null, 'reason' => $reason, 'duplicate_kind' => null, 'existing' => null];
            if ($reason === null) {
                $geometries[] = ['row' => $record['row'], 'geojson' => $items[$record['row']]['geojson']];
                $names[mb_strtolower($name, 'UTF-8')] = $name;
            }
        }
        $geometryResults = $this->validateGeometryBatch($geometries, $organizationId, $user);
        foreach ($geometryResults as $row => $result) {
            if (! $result['valid']) { $items[$row]['valid'] = false; $items[$row]['reason'] = $result['reason']; }
        }
        $existing = $this->existingZones($organizationId, array_values($names), $user);
        foreach ($items as &$item) {
            if (! $item['valid']) continue;
            $key = mb_strtolower($item['name'], 'UTF-8');
            if (isset($seen[$key])) $item['duplicate_kind'] = 'file';
            else {
                $seen[$key] = true;
                if (isset($existing[$key])) {
                    $item['duplicate_kind'] = 'existing';
                    $item['existing'] = $existing[$key];
                }
            }
        }
        unset($item);
        return array_values($items);
    }

    private function validateGeometryBatch(array $geometries, int $organizationId, User $user): array
    {
        if ($geometries === []) return [];
        $values = [];
        $bindings = [];
        foreach ($geometries as $geometry) {
            $values[] = '(?, ?::text)';
            $bindings[] = (int) $geometry['row'];
            $bindings[] = $geometry['geojson'];
        }
        try {
            $rows = DB::select('WITH input(idx, geojson) AS (VALUES '.implode(',', $values).'), candidate AS (
                    SELECT idx, ST_SetSRID(ST_GeomFromGeoJSON(geojson), 4326) AS geom FROM input
                ) SELECT idx, ST_IsValid(geom) AS valid, GeometryType(geom) AS geometry_type, ST_SRID(geom) AS srid,
                    ST_XMin(Box3D(geom)) AS min_x, ST_XMax(Box3D(geom)) AS max_x,
                    ST_YMin(Box3D(geom)) AS min_y, ST_YMax(Box3D(geom)) AS max_y FROM candidate', $bindings);
        } catch (\Throwable) {
            $rows = [];
        }
        $results = [];
        foreach ($rows as $row) {
            $typeValid = in_array(strtoupper($row->geometry_type), ['POLYGON', 'MULTIPOLYGON'], true);
            $valid = in_array($row->valid, [true, 1, '1', 't', 'true'], true) && $typeValid && (int) $row->srid === 4326
                && (float) $row->min_x >= -180 && (float) $row->max_x <= 180 && (float) $row->min_y >= -90 && (float) $row->max_y <= 90;
            $results[(int) $row->idx] = ['valid' => $valid, 'reason' => $valid ? null : 'هندسه نامعتبر، خارج از محدوده WGS84 یا غیر Polygon است.'];
        }
        foreach ($geometries as $geometry) {
            if (! isset($results[$geometry['row']])) {
                try {
                    $row = DB::selectOne('SELECT ST_IsValid(g) AS valid, GeometryType(g) AS geometry_type, ST_SRID(g) AS srid,
                        ST_XMin(Box3D(g)) AS min_x, ST_XMax(Box3D(g)) AS max_x, ST_YMin(Box3D(g)) AS min_y, ST_YMax(Box3D(g)) AS max_y
                        FROM (SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS g) AS candidate', [$geometry['geojson']]);
                    $valid = $row && $this->dbBool($row->valid) && in_array(strtoupper($row->geometry_type), ['POLYGON', 'MULTIPOLYGON'], true)
                        && (int) $row->srid === 4326 && (float) $row->min_x >= -180 && (float) $row->max_x <= 180
                        && (float) $row->min_y >= -90 && (float) $row->max_y <= 90;
                    $results[$geometry['row']] = ['valid' => $valid, 'reason' => $valid ? null : 'Invalid geometry, CRS, or bounds.'];
                } catch (\Throwable) {
                    $results[$geometry['row']] = ['valid' => false, 'reason' => 'PostGIS rejected geometry.'];
                }
            }
        }
        if ($user->hasRole('boss')) {
            $values = [];
            $bindings = [];
            foreach ($geometries as $geometry) {
                if (! ($results[(int) $geometry['row']]['valid'] ?? false)) continue;
                $values[] = '(?, ?::text)';
                $bindings[] = (int) $geometry['row'];
                $bindings[] = $geometry['geojson'];
            }
            if ($values === []) return $results;
            $bindings[] = $user->zone_id;
            $bindings[] = $organizationId;
            $containedRows = DB::select('WITH input(idx, geojson) AS (VALUES '.implode(',', $values).'), candidate AS (
                    SELECT idx, ST_SetSRID(ST_GeomFromGeoJSON(geojson), 4326) AS geom FROM input
                ) SELECT candidate.idx, ST_Covers(boundary.geom, candidate.geom) AS contained
                FROM candidate CROSS JOIN zone AS boundary
                WHERE boundary.id = ? AND boundary.organization_id = ? AND boundary.geom IS NOT NULL AND ST_IsValid(boundary.geom)', $bindings);
            $contained = [];
            foreach ($containedRows as $row) $contained[(int) $row->idx] = $this->dbBool($row->contained);
            foreach ($geometries as $geometry) {
                $rowId = (int) $geometry['row'];
                if (isset($results[$rowId]) && $results[$rowId]['valid'] && ! ($contained[$rowId] ?? false)) {
                    $results[$rowId] = ['valid' => false, 'reason' => 'Geometry must be entirely within the Boss boundary.'];
                }
            }
        }
        return $results;
    }

    private function existingZones(int $organizationId, array $names, User $user): array
    {
        if ($names === []) return [];
        $bindings = array_map(static fn ($name) => mb_strtolower(trim($name), 'UTF-8'), $names);
        $slots = implode(',', array_fill(0, count($bindings), '?'));
        $rows = DB::select("SELECT z.id, z.name, z.creator_id,
                EXISTS(SELECT 1 FROM users u WHERE u.zone_id = z.id) AS has_users,
                EXISTS(SELECT 1 FROM riser r WHERE r.geom IS NOT NULL AND ST_Intersects(r.geom, z.geom)) AS has_risers,
                EXISTS(SELECT 1 FROM maintenance_cases mc JOIN riser r ON r.id = mc.riser_id WHERE ST_Intersects(r.geom, z.geom)) AS has_cases
            FROM zone z WHERE z.organization_id = ? AND LOWER(BTRIM(z.name)) IN ($slots)", array_merge([$organizationId], $bindings));
        $result = [];
        foreach ($rows as $row) {
            $result[mb_strtolower(trim($row->name), 'UTF-8')] = [
                'id' => (int) $row->id, 'name' => $row->name,
                'linked' => $this->dbBool($row->has_users) || $this->dbBool($row->has_risers) || $this->dbBool($row->has_cases),
                'update_allowed' => ! $user->hasRole('boss') || ((int) $row->creator_id === (int) $user->id && (int) $row->id !== (int) $user->zone_id),
            ];
        }
        return $result;
    }

    private function writeNormalized(string $path, array $mapping, User $user, string $duplicateMode, bool $skipInvalid): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $seen = [];
        foreach ($this->readBatches($path) as $batch) {
            $classified = $this->classifyBatch($batch, (int) $mapping['organization_id'], $mapping, $seen, $user);
            foreach ($classified as $item) {
                if (! $item['valid']) {
                    if (! $skipInvalid) throw ValidationException::withMessages(['skip_invalid' => 'هندسه نامعتبر پیش از ثبت نهایی پیدا شد؛ تراکنش لغو شد.']);
                    $result['failed']++;
                    continue;
                }
                if ($item['duplicate_kind'] === 'file') { $result['skipped']++; continue; }
                if ($item['duplicate_kind'] === 'existing') {
                    if ($duplicateMode === 'reject') throw ValidationException::withMessages(['duplicate_mode' => 'در زمان ثبت نهایی مورد تکراری پیدا شد؛ کل تراکنش لغو شد.']);
                    if ($duplicateMode !== 'update' || $item['existing']['linked'] || ! $item['existing']['update_allowed']) { $result['skipped']++; continue; }
                    $zone = Zone::query()->findOrFail($item['existing']['id']);
                    $before = ['id' => $zone->id, 'name' => $zone->name, 'organization_id' => $zone->organization_id,
                        'geometry_sha256' => hash('sha256', (string) DB::scalar('SELECT encode(ST_AsEWKB(geom), ?) FROM zone WHERE id = ?', ['hex', $zone->id]))];
                    DB::update('UPDATE zone SET name = ?, geom = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), updated_at = now() WHERE id = ?', [$item['name'], $item['geojson'], $zone->id]);
                    app(AuditLogger::class)->record('zone.import_updated', $zone, $before, ['id' => $zone->id, 'name' => $item['name'], 'organization_id' => $zone->organization_id, 'geometry_sha256' => hash('sha256', $item['geojson'])]);
                    $result['updated']++;
                    continue;
                }
                $inserted = DB::selectOne('INSERT INTO zone (name, organization_id, creator_id, geom, created_at, updated_at)
                    VALUES (?, ?, ?, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), now(), now()) RETURNING id',
                    [$item['name'], $mapping['organization_id'], $user->id, $item['geojson']]);
                $zone = Zone::query()->findOrFail($inserted->id);
                app(AuditLogger::class)->record('zone.imported', $zone, null, ['id' => $zone->id, 'name' => $zone->name,
                    'organization_id' => $zone->organization_id, 'creator_id' => $user->id, 'geometry_sha256' => hash('sha256', $item['geojson'])]);
                $result['created']++;
            }
        }
        return $result;
    }

    private function readBatches(string $path): \Generator
    {
        $file = new \SplFileObject($path, 'rb');
        $batch = [];
        $row = 0;
        while (! $file->eof()) {
            $line = trim((string) $file->fgets(), "\x1e\r\n\t ");
            if ($line === '') continue;
            $row++;
            try { $feature = json_decode($line, true, 128, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { $feature = []; }
            $batch[] = ['row' => $row, 'feature' => $feature];
            if (count($batch) >= 100) { yield $batch; $batch = []; }
        }
        if ($batch !== []) yield $batch;
    }

    private function validateMapping(array $meta, User $user, array $mapping): array
    {
        $field = $mapping['name_field'] ?? null;
        $fields = collect($meta['fields'] ?? [])->pluck('name')->all();
        if (! is_string($field) || ! in_array($field, $fields, true)) {
            throw ValidationException::withMessages(['name_field' => 'ستون انتخاب‌شده برای نام در DBF وجود ندارد.']);
        }
        $organizationId = filter_var($mapping['organization_id'] ?? null, FILTER_VALIDATE_INT);
        $allowedIds = $this->organizationsFor($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (! $organizationId || ! in_array((int) $organizationId, $allowedIds, true)) {
            throw ValidationException::withMessages(['organization_id' => 'سازمان انتخاب‌شده خارج از محدوده مجاز شماست.']);
        }
        if ($user->hasRole('boss') && ! app(ZoneBoundaryService::class)->hasValidBoundary($user)) {
            throw ValidationException::withMessages(['organization_id' => 'محدوده اصلی معتبر برای Boss در دسترس نیست.']);
        }
        $encoding = strtoupper(trim((string) ($mapping['encoding'] ?? '')));
        $encoding = match ($encoding) { 'WINDOWS-1256', '1256' => 'CP1256', 'WINDOWS-1252', '1252' => 'CP1252', default => $encoding };
        if (! in_array($encoding, self::ENCODINGS, true)) {
            throw ValidationException::withMessages(['encoding' => 'Encoding پشتیبانی‌نشده است.']);
        }
        return ['name_field' => $field, 'organization_id' => (int) $organizationId, 'encoding' => $encoding];
    }

    private function revalidateAndExtract(array $meta, User $user, string $work): array
    {
        $zipPath = $this->folder($user, $meta['token']).DIRECTORY_SEPARATOR.'source.zip';
        if (! is_file($zipPath) || ! hash_equals((string) $meta['sha256'], hash_file('sha256', $zipPath))) {
            throw ValidationException::withMessages(['file' => 'فایل موقت تغییر کرده یا منقضی شده است؛ دوباره بارگذاری کنید.']);
        }
        $parts = $this->extractValidatedArchive($zipPath, $work);
        $current = $this->inspect($parts['shp']);
        if ($current['feature_count'] !== (int) $meta['feature_count'] || $current['geometry'] !== $meta['geometry']
            || $current['fields'] !== $meta['fields'] || $current['crs'] !== $meta['crs']) {
            throw ValidationException::withMessages(['file' => 'ساختار Shapefile با مرحله Mapping یکسان نیست؛ دوباره شروع کنید.']);
        }
        return $parts;
    }

    private function assertOwnerAndExpiry(array $meta, User $user): void
    {
        if (($meta['user_id'] ?? null) !== $user->id || ! isset($meta['created_at'], $meta['token'])
            || ! preg_match('/^[A-Za-z0-9]{64}$/', (string) $meta['token'])) abort(404);
        if ((int) $meta['created_at'] < time() - self::TOKEN_TTL) {
            $this->delete($meta, $user);
            throw ValidationException::withMessages(['file' => 'نشست موقت منقضی شده است؛ فایل را دوباره بارگذاری کنید.']);
        }
    }

    private function encodingHint(?string $cpg): ?string
    {
        if (! $cpg || ! is_file($cpg)) return null;
        $value = strtoupper(trim((string) file_get_contents($cpg, false, null, 0, 80)));
        return match ($value) { 'UTF-8', 'UTF8', '65001' => 'UTF-8', '1256', 'CP1256', 'WINDOWS-1256', 'ANSI 1256' => 'CP1256', '1252', 'CP1252', 'WINDOWS-1252' => 'CP1252', 'ISO-8859-6' => 'ISO-8859-6', default => null };
    }

    private function dbBool(mixed $value): bool { return in_array($value, [true, 1, '1', 't', 'true'], true); }
    private function gdalProcess(string $tool, array $arguments): Process
    {
        $environment = $this->gdalEnvironment($tool);
        $process = new Process([$this->binary($tool), ...$arguments]);
        $process->setEnv($environment);
        return $process;
    }

    /**
     * Resolve data paths from Laravel/.env configuration first, then the selected
     * executable's standard sibling share directory. This is also applied to
     * child processes started by PHP-FPM/Apache, whose environment can differ
     * from an interactive shell.
     */
    private function gdalEnvironment(string $tool): array
    {
        $binary = $this->binary($tool);
        if (isset($this->runtimeEnvironments[$binary])) return $this->runtimeEnvironments[$binary];
        $gdalData = $this->configuredDataPath('gdal_data', 'GDAL_DATA') ?? $this->inferDataPath($binary, 'gdal');
        $projData = $this->configuredDataPath('proj_data', 'PROJ_DATA')
            ?? $this->configuredDataPath('proj_data', 'PROJ_LIB')
            ?? $this->inferDataPath($binary, 'proj');

        $versionProcess = new Process([$binary, '--version']);
        $versionProcess->setTimeout(10);
        $versionProcess->setEnv(array_filter([
            'GDAL_DATA' => $gdalData,
            'PROJ_DATA' => $projData,
            'PROJ_LIB' => $projData,
        ], static fn ($value) => is_string($value) && $value !== ''));
        try {
            $versionProcess->run();
        } catch (\Throwable) {
            $this->logGdalConfigurationFailure($tool, 'Configured GDAL executable could not be started.');
            throw ValidationException::withMessages(['file' => $this->configurationErrorMessage()]);
        }
        $version = trim($versionProcess->getOutput());
        if (! $versionProcess->isSuccessful() || ! preg_match('/GDAL\s+(\d+)\.(\d+)/i', $version, $versionMatch)) {
            $this->logGdalConfigurationFailure($tool, 'Could not read GDAL version from configured executable.');
            throw ValidationException::withMessages(['file' => $this->configurationErrorMessage()]);
        }
        $otherTool = $tool === 'ogrinfo' ? 'ogr2ogr' : 'ogrinfo';
        $otherBinary = $this->binary($otherTool);
        $otherVersion = new Process([$otherBinary, '--version']);
        $otherVersion->setTimeout(10);
        $otherVersion->setEnv(array_filter([
            'GDAL_DATA' => $gdalData,
            'PROJ_DATA' => $projData,
            'PROJ_LIB' => $projData,
        ], static fn ($value) => is_string($value) && $value !== ''));
        try {
            $otherVersion->run();
        } catch (\Throwable) {
            $this->logGdalConfigurationFailure($tool, 'Configured companion GDAL executable could not be started.');
            throw ValidationException::withMessages(['file' => $this->configurationErrorMessage()]);
        }
        if (! $otherVersion->isSuccessful()
            || ! preg_match('/GDAL\s+(\d+)\.(\d+)/i', $otherVersion->getOutput(), $otherVersionMatch)
            || $otherVersionMatch[1] !== $versionMatch[1]
            || $otherVersionMatch[2] !== $versionMatch[2]) {
            $this->logGdalConfigurationFailure($tool, 'ogrinfo and ogr2ogr must use the same GDAL major/minor version.');
            throw ValidationException::withMessages(['file' => $this->configurationErrorMessage()]);
        }

        $gdalDataFile = ((int) $versionMatch[1] >= 3) ? 'epsg.wkt' : 'gcs.csv';
        if (! $gdalData || ! is_dir($gdalData) || ! is_file(rtrim($gdalData, '\\/').DIRECTORY_SEPARATOR.$gdalDataFile)) {
            $this->logGdalConfigurationFailure($tool, 'GDAL data directory is missing the file required by '.$version.'.');
            throw ValidationException::withMessages(['file' => $this->configurationErrorMessage()]);
        }
        if (! $projData || ! is_dir($projData) || ! is_file(rtrim($projData, '\\/').DIRECTORY_SEPARATOR.'proj.db')) {
            $this->logGdalConfigurationFailure($tool, 'PROJ data directory does not contain proj.db.');
            throw ValidationException::withMessages(['file' => $this->configurationErrorMessage()]);
        }

        return $this->runtimeEnvironments[$binary] = [
            'GDAL_DATA' => $gdalData,
            'PROJ_DATA' => $projData,
            'PROJ_LIB' => $projData,
        ];
    }

    private function configuredDataPath(string $configKey, string $environmentKey): ?string
    {
        $path = config('shapefile.'.$configKey);
        if (! is_string($path) || trim($path) === '') $path = getenv($environmentKey) ?: null;
        if (! is_string($path) || trim($path) === '') return null;
        $path = trim($path, " \t\n\r\0\x0B\"");
        if (! is_dir($path)) {
            $this->logGdalConfigurationFailure($configKey, 'Configured data directory does not exist.');
            throw ValidationException::withMessages(['file' => $this->configurationErrorMessage()]);
        }
        return $path;
    }

    private function inferDataPath(string $binary, string $kind): ?string
    {
        $path = $this->resolveBinaryPath($binary);
        if (! $path) return null;
        $directory = dirname($path);
        for ($depth = 0; $depth < 5; $depth++) {
            $candidate = $directory.DIRECTORY_SEPARATOR.'share'.DIRECTORY_SEPARATOR.$kind;
            if (is_dir($candidate)) return $candidate;
            $parent = dirname($directory);
            if ($parent === $directory) break;
            $directory = $parent;
        }
        return null;
    }

    private function resolveBinaryPath(string $binary): ?string
    {
        if (is_file($binary)) return realpath($binary) ?: $binary;
        $pathValue = getenv('PATH') ?: '';
        foreach (explode(PATH_SEPARATOR, $pathValue) as $directory) {
            foreach (['', '.exe', '.bat', '.cmd'] as $suffix) {
                $candidate = rtrim($directory, '\\/').DIRECTORY_SEPARATOR.$binary.$suffix;
                if (is_file($candidate)) return realpath($candidate) ?: $candidate;
            }
        }
        return null;
    }

    private function logGdalFailure(string $operation, Process $process): void
    {
        $error = strtolower($process->getErrorOutput());
        $signature = match (true) {
            str_contains($error, 'gcs.csv'), str_contains($error, 'epsg support file') => 'missing_gdal_crs_data',
            str_contains($error, 'proj.db'), str_contains($error, 'proj_create') => 'missing_or_incompatible_proj_data',
            str_contains($error, 'failed to process srs definition') => 'unreadable_crs_definition',
            default => 'gdal_operation_failed',
        };
        Log::error('Shapefile GDAL operation failed.', [
            'operation' => $operation,
            'binary' => basename($this->binary($operation === 'inspect' ? 'ogrinfo' : 'ogr2ogr')),
            'signature' => $signature,
        ]);
    }

    private function logGdalConfigurationFailure(string $context, string $diagnostic): void
    {
        Log::error('Shapefile GDAL/PROJ runtime is misconfigured.', ['context' => $context, 'diagnostic' => $diagnostic]);
    }

    private function gdalFailureMessage(string $error, string $operation): string
    {
        if (preg_match('/gcs\.csv|proj\.db|PROJ|EPSG support file|Failed to process SRS definition/i', $error)) {
            return $this->configurationErrorMessage();
        }
        return 'عملیات '.$operation.' موفق نبود؛ مختصات بدون تبدیل وارد نمی‌شود. فایل PRJ و CRS را بررسی کنید.';
    }

    private function configurationErrorMessage(): string
    {
        return 'ابزار تبدیل مختصات GIS به‌درستی پیکربندی نشده است. لطفاً تنظیمات GDAL/PROJ بررسی شود.';
    }

    private function binary(string $name): string { return (string) config('shapefile.'.$name, $name); }
    private function relativeFolder(User $user, string $token): string { return 'zone-imports/'.$user->id.'/'.$token; }
    private function folder(User $user, string $token): string { return storage_path('app/private/'.$this->relativeFolder($user, $token)); }
}
