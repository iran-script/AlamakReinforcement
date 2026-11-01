<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gis_map_settings', function (Blueprint $table): void {
            $table->string('name', 120)->default('Default');
            $table->string('service_type', 30)->default('xyz_raster');
            $table->string('style_url', 1000)->nullable();
            $table->unsignedSmallInteger('min_zoom')->default(0);
            $table->unsignedSmallInteger('max_zoom')->default(22);
            $table->dropUnique('gis_map_settings_organization_id_unique');
        });

        DB::statement('DROP INDEX IF EXISTS gis_map_settings_global_unique');
        DB::statement('CREATE UNIQUE INDEX gis_map_settings_global_profile_name_unique ON gis_map_settings (name) WHERE organization_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX gis_map_settings_org_profile_name_unique ON gis_map_settings (organization_id, name) WHERE organization_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX gis_map_settings_global_active_unique ON gis_map_settings ((1)) WHERE organization_id IS NULL AND is_active = true');
        DB::statement('CREATE UNIQUE INDEX gis_map_settings_org_active_unique ON gis_map_settings (organization_id) WHERE organization_id IS NOT NULL AND is_active = true');
    }

    public function down(): void
    {
        $multipleScopes = DB::table('gis_map_settings')->select('organization_id')
            ->groupBy('organization_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($multipleScopes) {
            throw new \RuntimeException('Cannot roll back GIS profiles while a scope has multiple profiles; remove extra profiles first.');
        }

        foreach ([
            'gis_map_settings_global_active_unique', 'gis_map_settings_org_active_unique',
            'gis_map_settings_global_profile_name_unique', 'gis_map_settings_org_profile_name_unique',
        ] as $index) DB::statement("DROP INDEX IF EXISTS {$index}");

        Schema::table('gis_map_settings', function (Blueprint $table): void {
            $table->dropColumn(['name', 'service_type', 'style_url', 'min_zoom', 'max_zoom']);
            $table->unique('organization_id');
        });
        DB::statement('CREATE UNIQUE INDEX gis_map_settings_global_unique ON gis_map_settings ((1)) WHERE organization_id IS NULL');
    }
};
