<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_photos', function (Blueprint $table) {

            $table->foreignId('operation_id')
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('photo')->after('operation_id');

            $table->string('title')->nullable()->after('photo');

            $table->text('description')->nullable()->after('title');

            $table->integer('sort')->default(0)->after('description');

        });
    }

    public function down(): void
    {
        Schema::table('operation_photos', function (Blueprint $table) {

            $table->dropForeign(['operation_id']);

            $table->dropColumn([
                'operation_id',
                'photo',
                'title',
                'description',
                'sort'
            ]);

        });
    }
};