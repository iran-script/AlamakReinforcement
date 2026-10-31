<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('status', 30)->default('active');
            $table->jsonb('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('asset_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key', 80);
            $table->string('name');
            $table->string('geometry_kind', 30)->default('point');
            $table->jsonb('attribute_schema')->nullable();
            $table->jsonb('ui_schema')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_type_id')->constrained()->restrictOnDelete();
            $table->string('code', 120)->nullable();
            $table->string('name')->nullable();
            $table->string('status', 40)->default('active');
            $table->jsonb('properties')->nullable();
            $table->string('legacy_source', 80)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'asset_type_id']);
            $table->index(['legacy_source', 'legacy_id']);
            $table->unique(['organization_id', 'asset_type_id', 'code']);
        });

        DB::statement('ALTER TABLE assets ADD COLUMN geom geometry(Geometry, 4326) NULL');
        DB::statement('CREATE INDEX assets_geom_gist ON assets USING GIST (geom)');

        Schema::create('asset_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('from_asset_id')->constrained('assets')->cascadeOnDelete();
            $table->foreignId('to_asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('relation_type', 60);
            $table->jsonb('properties')->nullable();
            $table->timestamps();
            $table->unique(['from_asset_id', 'to_asset_id', 'relation_type']);
            $table->index(['organization_id', 'relation_type']);
        });

        Schema::create('spatial_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('scope_type', 50)->default('operational_area');
            $table->jsonb('properties')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'scope_type']);
        });

        DB::statement('ALTER TABLE spatial_scopes ADD COLUMN geom geometry(MultiPolygon, 4326) NULL');
        DB::statement('CREATE INDEX spatial_scopes_geom_gist ON spatial_scopes USING GIST (geom)');

        Schema::create('user_spatial_scopes', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('spatial_scope_id')->constrained()->cascadeOnDelete();
            $table->string('access_level', 30)->default('view');
            $table->timestamps();
            $table->primary(['user_id', 'spatial_scope_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('organization_id');
        });

        Schema::table('zone', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('zone', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropColumn('organization_id');
        });

        Schema::dropIfExists('user_spatial_scopes');
        DB::statement('DROP INDEX IF EXISTS spatial_scopes_geom_gist');
        Schema::dropIfExists('spatial_scopes');
        Schema::dropIfExists('asset_relations');
        DB::statement('DROP INDEX IF EXISTS assets_geom_gist');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('asset_types');
        Schema::dropIfExists('organizations');
    }
};
