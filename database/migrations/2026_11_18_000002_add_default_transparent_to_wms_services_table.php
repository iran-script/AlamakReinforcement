<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wms_services', function (Blueprint $table): void {
            $table->boolean('default_transparent')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('wms_services', function (Blueprint $table): void {
            $table->dropColumn('default_transparent');
        });
    }
};
