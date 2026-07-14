<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {

            $table->foreignId('riser_id')
                ->after('id')
                ->constrained('riser')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table
                ->after('riser_id')
                ->constrained('operation_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->after('operation_category_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('contractor_id')
                ->nullable()
                ->after('user_id')
                ->constrained('contractors')
                ->nullOnDelete();

            $table->date('operation_date')
                ->after('contractor_id');

            $table->time('start_time')
                ->nullable()
                ->after('operation_date');

            $table->time('end_time')
                ->nullable()
                ->after('start_time');

            $table->string('status')
                ->default('0')
                ->after('end_time');

            $table->string('priority')
                ->default('0')
                ->after('status');

            $table->decimal('total_cost',15,2)
                ->default(0)
                ->after('priority');

            $table->text('description')
                ->nullable()
                ->after('total_cost');

        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {

            $table->dropForeign(['riser_id']);
            $table->dropForeign(['operation_category_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['contractor_id']);

            $table->dropColumn([
                'riser_id',
                'operation_category_id',
                'user_id',
                'contractor_id',
                'operation_date',
                'start_time',
                'end_time',
                'status',
                'priority',
                'total_cost',
                'description'
            ]);
        });
    }
};
