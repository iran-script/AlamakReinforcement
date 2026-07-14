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
        Schema::create('menus', function (Blueprint $table) {

            $table->id();

            // والد
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('menus')
                ->cascadeOnDelete();

            // عنوان
            $table->string('title');

            // آیکون bootstrap-icons
            $table->string('icon')->nullable();

            // route
            $table->string('route')->nullable();

            // permission
            $table->string('permission')->nullable();

            // ترتیب
            $table->integer('sort')->default(0);

            // فعال؟
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
