<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gis_map_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('base_tile_url', 1000);
            $table->string('base_tile_attribution', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('organization_id');
        });
        DB::statement('CREATE UNIQUE INDEX gis_map_settings_global_unique ON gis_map_settings ((1)) WHERE organization_id IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('gis_map_settings');
    }
};
