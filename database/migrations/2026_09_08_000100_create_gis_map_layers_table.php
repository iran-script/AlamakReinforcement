<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gis_map_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('title', 200);
            $table->string('type', 30);
            $table->string('endpoint', 1000);
            $table->string('wms_layers', 500)->nullable();
            $table->string('format', 50)->nullable();
            $table->string('version', 20)->nullable();
            $table->string('styles', 500)->nullable();
            $table->string('server_type', 50)->nullable();
            $table->boolean('transparent')->default(true);
            $table->unsignedSmallInteger('min_zoom')->default(0);
            $table->unsignedSmallInteger('max_zoom')->default(22);
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });

        DB::statement('CREATE UNIQUE INDEX gis_map_layers_global_key_unique ON gis_map_layers (key) WHERE organization_id IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('gis_map_layers');
    }
};
