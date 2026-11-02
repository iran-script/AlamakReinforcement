<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function ($table): void {
            $table->dropUnique('operations_maintenance_case_id_unique');
            $table->index('maintenance_case_id', 'operations_maintenance_case_id_index');
        });
    }

    public function down(): void
    {
        $duplicates = DB::table('operations')->select('maintenance_case_id')
            ->whereNotNull('maintenance_case_id')->groupBy('maintenance_case_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicates) {
            throw new RuntimeException('Cannot restore one-operation-per-case constraint while cases have multiple operations.');
        }

        Schema::table('operations', function ($table): void {
            $table->dropIndex('operations_maintenance_case_id_index');
            $table->unique('maintenance_case_id', 'operations_maintenance_case_id_unique');
        });
    }
};
