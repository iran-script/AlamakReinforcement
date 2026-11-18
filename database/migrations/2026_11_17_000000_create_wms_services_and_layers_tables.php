<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wms_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('url', 2000);
            $table->string('version', 20)->default('1.3.0');
            $table->boolean('is_active')->default(true);
            $table->json('capabilities_metadata')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'name']);
        });
        Schema::create('wms_layers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wms_service_id')->constrained('wms_services')->cascadeOnDelete();
            $table->string('layer_name', 500);
            $table->string('title', 500);
            $table->text('abstract')->nullable();
            $table->json('supported_crs')->nullable();
            $table->json('supported_formats')->nullable();
            $table->string('version', 20)->nullable();
            $table->boolean('queryable')->default(false);
            $table->string('legend_url', 2000)->nullable();
            $table->json('metadata')->nullable();
            $table->string('display_name', 500)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->boolean('default_visible')->default(false);
            $table->decimal('opacity', 3, 2)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['wms_service_id', 'layer_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wms_layers');
        Schema::dropIfExists('wms_services');
    }
};
