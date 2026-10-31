<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('zone')) {
            Schema::create('zone', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->timestamps();
            });

            DB::statement('ALTER TABLE zone ADD COLUMN geom geometry(Geometry, 4326) NULL');
            DB::statement('CREATE INDEX zone_geom_gist ON zone USING GIST (geom)');
        }

        if (! Schema::hasTable('riser')) {
            Schema::create('riser', function (Blueprint $table): void {
                $table->id();
                $table->string('r_giscode')->nullable();
                $table->string('giscode')->nullable();
                $table->string('status')->nullable();
                $table->timestamps();
            });

            DB::statement('ALTER TABLE riser ADD COLUMN geom geometry(Geometry, 4326) NULL');
            DB::statement('ALTER TABLE riser ADD COLUMN geom_3857 geometry(Geometry, 3857) NULL');
            DB::statement('CREATE INDEX riser_geom_gist ON riser USING GIST (geom)');
            DB::statement('CREATE INDEX riser_geom_3857_gist ON riser USING GIST (geom_3857)');
        }
    }

    public function down(): void
    {
        // These legacy tables may predate this migration on existing installations.
        // Do not drop them during rollback and risk deleting operational data.
    }
};
