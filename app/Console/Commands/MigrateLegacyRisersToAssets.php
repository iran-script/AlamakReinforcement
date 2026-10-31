<?php

namespace App\Console\Commands;

use App\Models\AssetType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MigrateLegacyRisersToAssets extends Command
{
    protected $signature = 'platform:migrate-risers {--dry-run : Report without writing} {--chunk=500 : Rows per batch}';

    protected $description = 'Idempotently copy legacy risers into the asset registry';

    public function handle(): int
    {
        foreach (['riser', 'assets', 'asset_types'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Required table {$table} is missing.");

                return self::FAILURE;
            }
        }

        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        if ($chunk === false) {
            $this->error('--chunk must be an integer between 1 and 10000.');

            return self::INVALID;
        }

        $columns = Schema::getColumnListing('riser');
        if (! in_array('id', $columns, true)) {
            $this->error('The riser table has no id column.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $type = $this->assetType($dryRun);
        if (! $dryRun && $type === null) {
            return self::FAILURE;
        }

        $geometryColumns = array_values(array_intersect(['geom', 'geom_3857'], $columns));
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
        $this->info(($dryRun ? '[DRY RUN] ' : '').'Migrating legacy risers...');
        $this->line('Geometry priority: '.($geometryColumns ? implode(', ', $geometryColumns) : 'none'));

        DB::table('riser')->orderBy('id')->chunkById($chunk, function ($rows) use ($columns, $geometryColumns, $type, $dryRun, &$counts): void {
            foreach ($rows as $riser) {
                try {
                    $data = $this->map($riser, $columns, $geometryColumns);
                    $asset = $type ? $this->assetQuery($type->id, $riser->id)->first() : null;

                    if (! $asset) {
                        $counts['created']++;
                        if (! $dryRun) {
                            $this->create($type->id, $riser->id, $data);
                        }
                    } elseif ($this->changed($asset, $riser->id, $data)) {
                        $counts['updated']++;
                        if (! $dryRun) {
                            $this->update($asset->id, $riser->id, $data);
                        }
                    } else {
                        $counts['skipped']++;
                    }
                } catch (Throwable $e) {
                    $counts['failed']++;
                    $this->warn("Riser {$riser->id} failed: {$e->getMessage()}");
                }
            }
        }, 'id');

        $this->table(['Created', 'Updated', 'Skipped', 'Failed'], [array_values($counts)]);
        if ($dryRun) {
            $this->comment('No asset type or asset data was written.');
        }

        return $counts['failed'] ? self::FAILURE : self::SUCCESS;
    }

    private function assetType(bool $dryRun): ?AssetType
    {
        $type = AssetType::query()->whereNull('organization_id')->where('key', 'riser')->oldest('id')->first();
        if ($type || $dryRun) {
            if (! $type) {
                $this->comment("Global AssetType 'riser' would be created.");
            }

            return $type;
        }

        try {
            return DB::transaction(function (): AssetType {
                DB::statement("SELECT pg_advisory_xact_lock(hashtext('platform:asset-type:riser'))");

                return AssetType::query()->firstOrCreate(
                    ['organization_id' => null, 'key' => 'riser'],
                    ['name' => 'Riser', 'geometry_kind' => 'point', 'is_active' => true]
                );
            });
        } catch (Throwable $e) {
            $this->error("Cannot resolve riser AssetType: {$e->getMessage()}");

            return null;
        }
    }

    private function map(object $riser, array $columns, array $geometryColumns): array
    {
        $code = $this->first($riser, $columns, ['r_giscode', 'giscode', 'code']);
        $name = $this->first($riser, $columns, ['name', 'title', 'address']);
        $status = $this->first($riser, $columns, ['status', 'state']) ?? 'active';
        $excluded = array_flip(array_merge(['id', 'created_at', 'updated_at'], $geometryColumns));
        $properties = [];
        foreach ($columns as $column) {
            if (! isset($excluded[$column])) {
                $properties[$column] = $riser->{$column} ?? null;
            }
        }

        return [
            'code' => $code === null ? null : mb_substr((string) $code, 0, 120),
            'name' => $name === null ? null : mb_substr((string) $name, 0, 255),
            'status' => mb_substr((string) $status, 0, 40),
            'properties' => json_encode($properties, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'geometry' => $this->geometrySql($geometryColumns),
        ];
    }

    private function first(object $row, array $columns, array $candidates): mixed
    {
        foreach ($candidates as $column) {
            $value = in_array($column, $columns, true) ? ($row->{$column} ?? null) : null;
            if ($value !== null && trim((string) $value) !== '') {
                return $value;
            }
        }

        return null;
    }

    private function geometrySql(array $columns): string
    {
        if (! $columns) {
            return 'NULL';
        }
        $items = array_map(function (string $column): string {
            $column = '"'.str_replace('"', '""', $column).'"';
            $fallback = $column === '"geom_3857"' ? 3857 : 4326;

            return "CASE WHEN {$column} IS NULL THEN NULL WHEN ST_SRID({$column}) = 4326 THEN ST_Force2D({$column}) "
                ."WHEN ST_SRID({$column}) = 0 THEN ST_Transform(ST_SetSRID(ST_Force2D({$column}), {$fallback}), 4326) "
                ."ELSE ST_Transform(ST_Force2D({$column}), 4326) END";
        }, $columns);

        return 'COALESCE('.implode(', ', $items).')';
    }

    private function assetQuery(int $typeId, int|string $legacyId)
    {
        return DB::table('assets')->whereNull('organization_id')->where('asset_type_id', $typeId)
            ->where('legacy_source', 'riser')->where('legacy_id', $legacyId)->oldest('id');
    }

    private function changed(object $asset, int|string $legacyId, array $data): bool
    {
        if ($asset->code !== $data['code'] || $asset->name !== $data['name'] || $asset->status !== $data['status']
            || $this->json($asset->properties) !== $this->json($data['properties'])) {
            return true;
        }

        $matches = DB::selectOne(
            "SELECT CASE WHEN a.geom IS NULL AND r.geom IS NULL THEN true WHEN a.geom IS NULL OR r.geom IS NULL THEN false ELSE ST_Equals(a.geom, r.geom) END AS matches FROM assets a CROSS JOIN LATERAL (SELECT {$data['geometry']} AS geom FROM riser WHERE id = ?) r WHERE a.id = ?",
            [$legacyId, $asset->id]
        );

        return ! (bool) ($matches->matches ?? false);
    }

    private function json(?string $json): string
    {
        return $json === null ? 'null' : json_encode(json_decode($json, true, 512, JSON_THROW_ON_ERROR), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function create(int $typeId, int|string $legacyId, array $data): void
    {
        DB::transaction(function () use ($typeId, $legacyId, $data): void {
            DB::statement('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ["riser:{$legacyId}"]);
            if ($asset = $this->assetQuery($typeId, $legacyId)->first()) {
                $this->update($asset->id, $legacyId, $data);

                return;
            }
            $id = DB::table('assets')->insertGetId([
                'organization_id' => null, 'asset_type_id' => $typeId, 'code' => $data['code'], 'name' => $data['name'],
                'status' => $data['status'], 'properties' => $data['properties'], 'legacy_source' => 'riser',
                'legacy_id' => $legacyId, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->geometry($id, $legacyId, $data['geometry']);
        });
    }

    private function update(int $assetId, int|string $legacyId, array $data): void
    {
        DB::transaction(function () use ($assetId, $legacyId, $data): void {
            DB::table('assets')->where('id', $assetId)->update([
                'code' => $data['code'], 'name' => $data['name'], 'status' => $data['status'],
                'properties' => $data['properties'], 'updated_at' => now(),
            ]);
            $this->geometry($assetId, $legacyId, $data['geometry']);
        });
    }

    private function geometry(int $assetId, int|string $legacyId, string $sql): void
    {
        DB::update("UPDATE assets SET geom = (SELECT {$sql} FROM riser WHERE id = ?) WHERE id = ?", [$legacyId, $assetId]);
    }
}
