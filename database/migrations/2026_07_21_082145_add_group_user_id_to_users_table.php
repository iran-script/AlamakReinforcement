<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->foreignId('group_user_id')
                ->nullable()
                ->after('id')
                ->constrained('group_users')
                ->nullOnDelete();

        });
    }


    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropForeign([
                'group_user_id'
            ]);

            $table->dropColumn('group_user_id');

        });
    }

};