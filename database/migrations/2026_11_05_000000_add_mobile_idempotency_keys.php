<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_cases', function (Blueprint $table): void {
            $table->uuid('client_uuid')->nullable();
            $table->unique('client_uuid', 'maintenance_cases_client_uuid_unique');
        });
        Schema::table('operations', function (Blueprint $table): void {
            $table->uuid('client_uuid')->nullable();
            $table->unique('client_uuid', 'operations_client_uuid_unique');
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table): void {
            $table->dropUnique('operations_client_uuid_unique');
            $table->dropColumn('client_uuid');
        });
        Schema::table('maintenance_cases', function (Blueprint $table): void {
            $table->dropUnique('maintenance_cases_client_uuid_unique');
            $table->dropColumn('client_uuid');
        });
    }
};
