<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;


return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('operation_image', function (Blueprint $table) {
            $table->foreignId('riser_id')->nullable()->after('id')
                ->constrained('riser')->cascadeOnUpdate()->nullOnDelete();
        });

        // operation_id باید بتونه موقتاً خالی باشه تا وصل شدنش به عملیات
        DB::statement('ALTER TABLE operation_image ALTER COLUMN operation_id DROP NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_image');
    }
};
