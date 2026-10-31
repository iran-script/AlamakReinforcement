<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_order_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('default_priority', 20)->nullable();
            $table->unsignedInteger('default_sla_hours')->nullable();
            $table->jsonb('configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'key'], 'work_order_types_org_key_unique');
            $table->index(['organization_id', 'is_active']);
        });

        DB::statement('CREATE UNIQUE INDEX work_order_types_global_key_unique ON work_order_types (key) WHERE organization_id IS NULL');

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('work_order_type_id')->constrained('work_order_types')->restrictOnDelete();
            $table->string('code', 120)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 30)->default('draft');
            $table->string('priority', 20)->default('normal');
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained('contracts')->nullOnDelete();
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->jsonb('properties')->nullable();
            $table->string('legacy_source', 80)->nullable();
            $table->unsignedBigInteger('legacy_id')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'code'], 'work_orders_org_code_unique');
            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'priority']);
            $table->index(['organization_id', 'work_order_type_id']);
            $table->index(['organization_id', 'assigned_to']);
            $table->index(['organization_id', 'scheduled_at']);
            $table->index(['organization_id', 'due_at']);
            $table->index(['legacy_source', 'legacy_id']);
        });

        DB::statement('CREATE UNIQUE INDEX work_orders_global_code_unique ON work_orders (code) WHERE organization_id IS NULL AND code IS NOT NULL');
        DB::statement('ALTER TABLE work_orders ADD COLUMN geom geometry(Geometry, 4326) NULL');
        DB::statement('CREATE INDEX work_orders_geom_gist ON work_orders USING GIST (geom)');

        Schema::create('work_order_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->string('relationship_type', 30)->default('related');
            $table->timestamps();
            $table->unique(['work_order_id', 'asset_id'], 'work_order_assets_work_asset_unique');
            $table->index(['asset_id', 'relationship_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_assets');
        DB::statement('DROP INDEX IF EXISTS work_orders_geom_gist');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('work_order_types');
    }
};
