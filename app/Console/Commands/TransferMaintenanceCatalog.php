<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class TransferMaintenanceCatalog extends Command
{
    protected $signature = 'catalog:transfer-from-tamirat2
        {--execute : Write only after an explicit review of the dry-run report}
        {--source-host= : Source PostgreSQL host}
        {--source-port=5432 : Source PostgreSQL port}
        {--source-database=tamirat2 : Source PostgreSQL database}
        {--source-username= : Source PostgreSQL username}
        {--source-password= : Source PostgreSQL password}';

    protected $description = 'Plan or explicitly transfer operation/material catalog data from tamirat2';

    public function handle(): int
    {
        $source = $this->sourceConnection();
        $operationCategories = $this->read($source, 'operation_categories');
        $materialCategories = $this->read($source, 'material_categories');
        $materials = $this->read($source, 'materials');

        $plan = [
            'operation_categories' => $this->planCategories('operation_categories', $operationCategories),
            'material_categories' => $this->planCategories('material_categories', $materialCategories),
            'materials' => $this->planMaterials($materials, $materialCategories),
        ];

        $this->printPlan($plan);

        if (! $this->option('execute')) {
            $this->info('Dry-run only: no destination data was changed.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Write only the planned non-conflicting rows to alamak_dev?', false)) {
            $this->warn('Transfer cancelled.');

            return self::SUCCESS;
        }

        return $this->executePlan($plan);
    }

    private function sourceConnection(): PDO
    {
        $host = $this->option('source-host') ?: env('MIGRATION_SOURCE_DB_HOST', '127.0.0.1');
        $port = $this->option('source-port') ?: env('MIGRATION_SOURCE_DB_PORT', 5432);
        $database = $this->option('source-database') ?: env('MIGRATION_SOURCE_DB_DATABASE', 'tamirat2');
        $username = $this->option('source-username') ?: env('MIGRATION_SOURCE_DB_USERNAME');
        $password = $this->option('source-password') ?? env('MIGRATION_SOURCE_DB_PASSWORD');

        if (! $username) {
            throw new RuntimeException('Set MIGRATION_SOURCE_DB_USERNAME or pass --source-username.');
        }

        $pdo = new PDO(
            "pgsql:host={$host};port={$port};dbname={$database}",
            $username,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');

        return $pdo;
    }

    private function read(PDO $source, string $table): array
    {
        $allowed = ['operation_categories', 'material_categories', 'materials'];

        if (! in_array($table, $allowed, true)) {
            throw new RuntimeException('Unsupported source table.');
        }

        return $source->query("SELECT * FROM {$table} ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function planCategories(string $table, array $sourceRows): array
    {
        $destination = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        $sourceKeys = [];
        $items = [];

        foreach ($sourceRows as $row) {
            $key = $this->categoryKey($row);
            $status = 'new';
            $reason = null;

            if (isset($sourceKeys[$key])) {
                $status = 'conflict';
                $reason = 'duplicate source matching key';
            } else {
                $sourceKeys[$key] = true;
                foreach ($destination as $existing) {
                    if ($this->categoryKey($existing) !== $key) {
                        continue;
                    }

                    $status = $this->sameCategory($row, $existing) ? 'duplicate' : 'conflict';
                    $reason = $status === 'duplicate'
                        ? 'matching destination row already exists'
                        : 'destination row has conflicting values';
                    break;
                }
            }

            $items[] = compact('row', 'key', 'status', 'reason');
        }

        return ['table' => $table, 'items' => $items];
    }

    private function planMaterials(array $sourceRows, array $sourceCategories): array
    {
        $destination = DB::table('materials')->get()->map(fn ($row) => (array) $row)->all();
        $destinationCategories = DB::table('material_categories')->get()->map(fn ($row) => (array) $row)->all();
        $categoryKeys = [];

        foreach ($sourceCategories as $category) {
            $categoryKeys[(string) $category['id']] = $this->categoryKey($category);
        }

        $sourceKeys = [];
        $items = [];

        foreach ($sourceRows as $row) {
            $categoryKey = $categoryKeys[(string) $row['material_category_id']] ?? null;
            $key = $categoryKey === null ? null : $categoryKey.'|'.$this->textKey($row['title']).'|'.$this->textKey($row['unit']);
            $status = 'new';
            $reason = null;

            if ($key === null) {
                $status = 'conflict';
                $reason = 'source material references a missing category';
            } elseif (isset($sourceKeys[$key])) {
                $status = 'conflict';
                $reason = 'duplicate source matching key';
            } else {
                $sourceKeys[$key] = true;
                $destinationCategoryId = $this->destinationCategoryId($categoryKey, $destinationCategories);
                foreach ($destination as $existing) {
                    if ((string) $existing['material_category_id'] !== (string) $destinationCategoryId
                        || $this->textKey($existing['title']) !== $this->textKey($row['title'])
                        || $this->textKey($existing['unit']) !== $this->textKey($row['unit'])) {
                        continue;
                    }

                    $status = $this->sameMaterial($row, $existing) ? 'duplicate' : 'conflict';
                    $reason = $status === 'duplicate'
                        ? 'matching destination row already exists'
                        : 'destination row has conflicting values';
                    break;
                }
            }

            $items[] = compact('row', 'key', 'status', 'reason', 'categoryKey');
        }

        return ['table' => 'materials', 'items' => $items];
    }

    private function executePlan(array $plan): int
    {
        DB::beginTransaction();

        try {
            $categoryIds = [];
            foreach (['operation_categories', 'material_categories'] as $table) {
                foreach ($plan[$table]['items'] as $item) {
                    if ($item['status'] === 'conflict') {
                        continue;
                    }
                    $row = $item['row'];
                    if ($item['status'] === 'new') {
                        $id = DB::table($table)->insertGetId($this->categoryPayload($row));
                    } else {
                        $id = DB::table($table)->where(function ($query) use ($row) {
                            if ($this->normalizedCode($row['code'] ?? null) !== null) {
                                $query->where('code', $row['code']);
                            } else {
                                $query->whereNull('code')->whereRaw('LOWER(BTRIM(title)) = ?', [$this->textKey($row['title'])]);
                            }
                        })->value('id');
                    }
                    $categoryIds[$table][$item['key']] = $id;
                }
            }

            foreach ($plan['materials']['items'] as $item) {
                if ($item['status'] !== 'new') {
                    continue;
                }
                $row = $item['row'];
                $categoryId = $categoryIds['material_categories'][$item['categoryKey']] ?? null;
                if (! $categoryId) {
                    throw new RuntimeException('Could not resolve destination material category.');
                }
                DB::table('materials')->insert($this->materialPayload($row, $categoryId));
            }

            DB::commit();
            $this->info('Transfer completed. Existing destination rows were not overwritten.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            DB::rollBack();
            $this->error('Transfer rolled back: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function printPlan(array $plan): void
    {
        foreach ($plan as $name => $section) {
            $counts = array_count_values(array_column($section['items'], 'status'));
            $this->line(sprintf(
                '%s: new=%d duplicate=%d conflict=%d',
                $name,
                $counts['new'] ?? 0,
                $counts['duplicate'] ?? 0,
                $counts['conflict'] ?? 0
            ));
            foreach ($section['items'] as $item) {
                if ($item['status'] === 'conflict') {
                    $this->warn('  conflict source id '.$item['row']['id'].': '.$item['reason']);
                }
            }
        }
    }

    private function categoryKey(array $row): string
    {
        $code = $this->normalizedCode($row['code'] ?? null);

        return $code === null ? 'title:'.$this->textKey($row['title']) : 'code:'.$code;
    }

    private function destinationCategoryId(?string $categoryKey, array $categories): ?int
    {
        if ($categoryKey === null) {
            return null;
        }
        foreach ($categories as $category) {
            if ($this->categoryKey($category) === $categoryKey) {
                return (int) $category['id'];
            }
        }

        return null;
    }

    private function normalizedCode(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function textKey(?string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim((string) $value)));
    }

    private function sameCategory(array $source, array $destination): bool
    {
        return $this->textKey($source['title']) === $this->textKey($destination['title'])
            && (string) ($source['description'] ?? '') === (string) ($destination['description'] ?? '')
            && (bool) $source['is_active'] === (bool) $destination['is_active']
            && (int) $source['sort'] === (int) $destination['sort'];
    }

    private function sameMaterial(array $source, array $destination): bool
    {
        return $this->textKey($source['title']) === $this->textKey($destination['title'])
            && $this->textKey($source['unit']) === $this->textKey($destination['unit'])
            && (string) $source['default_price'] === (string) $destination['default_price']
            && (string) ($source['description'] ?? '') === (string) ($destination['description'] ?? '')
            && (bool) $source['is_active'] === (bool) $destination['is_active'];
    }

    private function categoryPayload(array $row): array
    {
        return [
            'title' => $row['title'], 'code' => $row['code'], 'description' => $row['description'],
            'is_active' => $row['is_active'], 'sort' => $row['sort'],
            'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'],
        ];
    }

    private function materialPayload(array $row, int $categoryId): array
    {
        return [
            'material_category_id' => $categoryId, 'title' => $row['title'], 'code' => $row['code'],
            'unit' => $row['unit'], 'default_price' => $row['default_price'], 'description' => $row['description'],
            'is_active' => $row['is_active'], 'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'],
        ];
    }
}
