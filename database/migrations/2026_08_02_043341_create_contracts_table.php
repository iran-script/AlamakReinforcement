<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('contractor_name');
            $table->string('contract_number')->unique();
            $table->string('supervisor_name');
            $table->date('start_date')->nullable();     // تاریخ شروع
            $table->date('end_date')->nullable();       // تاریخ پایان
            $table->decimal('amount', 15, 2)->nullable(); // مبلغ پیمان
            $table->text('description')->nullable();    // توضیحات
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
