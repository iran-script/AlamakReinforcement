<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });

        DB::statement("
            ALTER TABLE operations
            ADD COLUMN geom geometry(Point, 4326)
        ");

        DB::statement("
            CREATE INDEX operations_geom_gist
            ON operations
            USING GIST (geom)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('operations');
    }
};