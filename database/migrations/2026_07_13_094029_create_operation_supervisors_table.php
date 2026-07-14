<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_supervisors', function (Blueprint $table) {

            $table->id();

            $table->foreignId('operation_id')
                ->constrained('operations')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'need_revision'
            ])->default('pending');

            $table->text('comment')->nullable();

            $table->timestamp('approved_at')->nullable();

            $table->integer('order')->default(1);

            $table->timestamps();

            $table->unique(['operation_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_supervisors');
    }
};