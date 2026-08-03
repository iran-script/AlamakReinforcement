<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id_from')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id_to')->constrained('users')->cascadeOnDelete();

            $table->foreignId('riser_id')->constrained('riser')->cascadeOnDelete();
            $table->foreignId('operation_id')->nullable()->constrained('operations')->nullOnDelete();

            // کلید ماشین‌خوان مرحله — برای کوئری و منطق برنامه (title فقط برای نمایش کاربره)
            $table->string('type', 60);

            $table->string('title');
            $table->text('comment')->nullable();

            $table->timestamp('send_date');
            $table->timestamp('receive_date')->nullable();

            $table->timestamps();

            $table->index('user_id_to');   // کارتابل هر کاربر: where user_id_to = ? and receive_date is null
            $table->index(['riser_id', 'operation_id']); // تاریخچه کامل یک علمک/عملیات
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transfers');
    }
};