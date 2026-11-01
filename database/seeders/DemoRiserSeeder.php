<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DemoRiserSeeder extends Seeder
{
    private const PREFIX = 'TEST-RISER-';

    private const ZONE_NAME = 'TEST-ZONE-RISER-DEMO';

    private const STATUSES = ['default', 'pending', 'success', 'error'];

    public function run(): void
    {
        $this->assertSafeTarget();
        $this->assertExistingSchema();

        $result = DB::transaction(function (): array {
            // Serialize retries so two concurrent runs cannot pass the same existence checks.
            DB::selectOne("select pg_advisory_xact_lock(hashtext('demo-riser-seeder'))");

            $zone = $this->findOrCreateDemoZone();
            $codes = $this->codes();

            $this->assertNoUnexpectedDemoCodes($codes);

            $created = 0;
            $existing = 0;

            foreach ($codes as $index => $code) {
                $point = $this->pointFor($index);
                $matches = DB::table('riser')
                    ->where('r_giscode', $code)
                    ->orWhere('giscode', $code)
                    ->get(['id', 'r_giscode', 'giscode', 'status']);

                if ($matches->count() > 1) {
                    throw new RuntimeException("Conflicting existing rows found for {$code}; no rows were changed.");
                }

                if ($matches->isNotEmpty()) {
                    $row = $matches->first();
                    if ($row->r_giscode !== $code || $row->giscode !== $code) {
                        throw new RuntimeException("Existing row for {$code} does not have matching GIS codes; no rows were changed.");
                    }

                    $this->assertRiserGeometry((int) $row->id, $zone->id);
                    $existing++;

                    continue;
                }

                DB::selectOne(
                    'INSERT INTO riser (r_giscode, giscode, status, geom, geom_3857, created_at, updated_at)
                     VALUES (?, ?, ?,
                         ST_SetSRID(ST_MakePoint(?, ?), 4326),
                         ST_Transform(ST_SetSRID(ST_MakePoint(?, ?), 4326), 3857),
                         now(), now())
                     RETURNING id',
                    [
                        $code,
                        $code,
                        self::STATUSES[intdiv($index, 5)],
                        $point['longitude'],
                        $point['latitude'],
                        $point['longitude'],
                        $point['latitude'],
                    ]
                );

                $id = (int) DB::table('riser')->where('r_giscode', $code)->value('id');
                $this->assertRiserGeometry($id, $zone->id);
                $created++;
            }

            $this->assertFinalDemoSet($zone->id, $codes);

            $statusCounts = DB::table('riser')
                ->select('status', DB::raw('count(*) as count'))
                ->whereIn('r_giscode', $codes)
                ->groupBy('status')
                ->orderBy('status')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->status => (int) $row->count])
                ->all();

            $bounds = DB::selectOne(
                'SELECT min(ST_X(geom)) AS min_longitude,
                        max(ST_X(geom)) AS max_longitude,
                        min(ST_Y(geom)) AS min_latitude,
                        max(ST_Y(geom)) AS max_latitude
                 FROM riser WHERE r_giscode LIKE ?',
                [self::PREFIX.'%']
            );

            return [
                'zone_id' => (int) $zone->id,
                'created' => $created,
                'existing' => $existing,
                'codes' => $codes,
                'statuses' => $statusCounts,
                'bounds' => $bounds,
            ];
        });

        $this->command?->info(sprintf(
            'Demo risers ready: %d created, %d already present; zone %s (id %d).',
            $result['created'],
            $result['existing'],
            self::ZONE_NAME,
            $result['zone_id']
        ));
        $this->command?->line('Codes: '.implode(', ', $result['codes']));
        $this->command?->line('Status counts: '.json_encode($result['statuses'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $this->command?->line(sprintf(
            'Synthetic EPSG:4326 bounds: lon %s..%s, lat %s..%s.',
            $result['bounds']->min_longitude,
            $result['bounds']->max_longitude,
            $result['bounds']->min_latitude,
            $result['bounds']->max_latitude
        ));
    }

    private function assertSafeTarget(): void
    {
        if (config('app.env') !== 'local') {
            throw new RuntimeException('DemoRiserSeeder is restricted to APP_ENV=local.');
        }

        $connection = config('database.default');
        $connectionConfig = config("database.connections.{$connection}", []);
        if ($connection !== 'pgsql'
            || ($connectionConfig['host'] ?? null) !== '127.0.0.1'
            || (string) ($connectionConfig['port'] ?? '') !== '5432'
            || ($connectionConfig['database'] ?? null) !== 'alamakreinforcement') {
            throw new RuntimeException('Refusing to seed: expected pgsql at 127.0.0.1:5432/alamakreinforcement.');
        }

        $identity = DB::connection($connection)->selectOne(
            'select current_database() as database, inet_server_addr()::text as host,
                    inet_server_port() as port, current_user as username,
                    system_identifier::text as cluster_id
             from pg_control_system()'
        );

        if (($identity->database ?? null) !== 'alamakreinforcement'
            || ! in_array($identity->host ?? null, ['127.0.0.1', '127.0.0.1/32'], true)
            || (int) ($identity->port ?? 0) !== 5432) {
            throw new RuntimeException('Refusing to seed: connected database identity does not match the approved local target.');
        }

        $this->command?->line(sprintf(
            'Verified local target: %s@%s:%s/%s (cluster %s).',
            $identity->username,
            $identity->host,
            $identity->port,
            $identity->database,
            $identity->cluster_id
        ));
    }

    private function assertExistingSchema(): void
    {
        foreach ([
            'riser' => ['r_giscode', 'giscode', 'status', 'geom', 'geom_3857'],
            'zone' => ['name', 'geom'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Required existing table [{$table}] is missing; no schema will be created.");
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("Required existing column [{$table}.{$column}] is missing; no schema will be created.");
                }
            }
        }

        $geometryColumns = collect(DB::select(
            'select f_table_name, f_geometry_column, srid from geometry_columns
             where f_table_schema = ? and f_table_name in (?, ?)',
            ['public', 'riser', 'zone']
        ))->mapWithKeys(fn ($row) => ["{$row->f_table_name}.{$row->f_geometry_column}" => (int) $row->srid]);

        foreach (['riser.geom' => 4326, 'riser.geom_3857' => 3857, 'zone.geom' => 4326] as $geometry => $srid) {
            if ($geometryColumns->get($geometry) !== $srid) {
                throw new RuntimeException("Existing geometry [{$geometry}] must use SRID {$srid}; no schema will be changed.");
            }
        }
    }

    private function findOrCreateDemoZone(): object
    {
        $zone = DB::table('zone')->where('name', self::ZONE_NAME)->first(['id']);

        if (! $zone) {
            $zone = DB::selectOne(
                'INSERT INTO zone (name, geom, created_at, updated_at)
                 VALUES (?, ST_MakeEnvelope(49.49, 33.99, 49.51, 34.01, 4326), now(), now())
                 RETURNING id',
                [self::ZONE_NAME]
            );
        }

        $zoneCheck = DB::selectOne(
            'SELECT ST_SRID(geom) AS srid, ST_IsValid(geom) AS is_valid,
                    ST_GeometryType(geom) AS geometry_type
             FROM zone WHERE id = ?',
            [$zone->id]
        );

        if (! $zoneCheck || (int) $zoneCheck->srid !== 4326 || ! $zoneCheck->is_valid || $zoneCheck->geometry_type !== 'ST_Polygon') {
            throw new RuntimeException('The existing demo zone is not a valid EPSG:4326 polygon; it was left unchanged.');
        }

        return $zone;
    }

    private function assertNoUnexpectedDemoCodes(array $expectedCodes): void
    {
        $rows = DB::table('riser')
            ->where('r_giscode', 'like', self::PREFIX.'%')
            ->orWhere('giscode', 'like', self::PREFIX.'%')
            ->get(['r_giscode', 'giscode']);

        foreach ($rows as $row) {
            if (! in_array($row->r_giscode, $expectedCodes, true) || ! in_array($row->giscode, $expectedCodes, true)) {
                throw new RuntimeException('Unexpected TEST-RISER code already exists; refusing to alter or mix demo datasets.');
            }
        }
    }

    private function assertRiserGeometry(int $riserId, int $zoneId): void
    {
        $geometry = DB::selectOne(
            'SELECT ST_SRID(geom) AS geom_srid,
                    ST_SRID(geom_3857) AS geom_3857_srid,
                    ST_IsValid(geom) AS geom_valid,
                    ST_IsValid(geom_3857) AS geom_3857_valid,
                    ST_GeometryType(geom) AS geometry_type,
                    ST_Intersects(geom, (SELECT geom FROM zone WHERE id = ?)) AS inside_zone
             FROM riser WHERE id = ?',
            [$zoneId, $riserId]
        );

        if (! $geometry
            || (int) $geometry->geom_srid !== 4326
            || (int) $geometry->geom_3857_srid !== 3857
            || ! $geometry->geom_valid
            || ! $geometry->geom_3857_valid
            || $geometry->geometry_type !== 'ST_Point'
            || ! $geometry->inside_zone) {
            throw new RuntimeException("Demo riser [{$riserId}] has invalid or out-of-zone geometry; the transaction was rolled back.");
        }
    }

    private function assertFinalDemoSet(int $zoneId, array $codes): void
    {
        $prefixCount = DB::table('riser')->where('r_giscode', 'like', self::PREFIX.'%')->count();
        if ($prefixCount !== 20) {
            throw new RuntimeException("Expected exactly 20 TEST-RISER rows, found {$prefixCount}; the transaction was rolled back.");
        }

        foreach ($codes as $code) {
            $row = DB::table('riser')->where('r_giscode', $code)->first(['id', 'r_giscode', 'giscode']);
            if (! $row || $row->r_giscode !== $code || $row->giscode !== $code) {
                throw new RuntimeException("Final demo code verification failed for {$code}; the transaction was rolled back.");
            }

            $this->assertRiserGeometry((int) $row->id, $zoneId);
        }

        $zoneCount = DB::table('zone')->where('name', self::ZONE_NAME)->count();
        if ($zoneCount !== 1) {
            throw new RuntimeException('Expected one named demo zone; the transaction was rolled back.');
        }
    }

    private function codes(): array
    {
        return array_map(fn (int $number) => self::PREFIX.sprintf('%03d', $number), range(1, 20));
    }

    /** @return array{longitude: float, latitude: float} */
    private function pointFor(int $index): array
    {
        return [
            'longitude' => 49.5 + ($index % 5) * 0.0005,
            'latitude' => 34.0 + intdiv($index, 5) * 0.0005,
        ];
    }
}
