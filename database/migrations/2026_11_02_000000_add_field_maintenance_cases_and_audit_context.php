<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('riser_id')->constrained('riser')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_group_id')->nullable()->constrained('group_users')->nullOnDelete();
            $table->foreignId('operation_category_id')->nullable()->constrained('operation_categories')->nullOnDelete();
            $table->string('status', 32)->default('open');
            $table->string('priority', 32)->default('normal');
            $table->text('description')->nullable();
            $table->timestampTz('identified_at');
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->index(['riser_id', 'status']);
            $table->index(['assigned_group_id', 'status']);
            $table->index(['reported_by', 'identified_at']);
        });

        Schema::table('operations', function (Blueprint $table): void {
            $table->foreignId('maintenance_case_id')->nullable()->unique()->constrained('maintenance_cases')->nullOnDelete();
            $table->foreignId('group_user_id')->nullable()->constrained('group_users')->nullOnDelete();
            $table->index(['group_user_id', 'operation_date']);
        });

        Schema::table('operation_image', function (Blueprint $table): void {
            $table->foreignId('maintenance_case_id')->nullable()->constrained('maintenance_cases')->nullOnDelete();
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('actor_role', 255)->nullable();
            $table->foreignId('zone_id')->nullable()->constrained('zone')->nullOnDelete();
            $table->foreignId('riser_id')->nullable()->constrained('riser')->nullOnDelete();
            $table->foreignId('operation_id')->nullable()->constrained('operations')->nullOnDelete();
            $table->string('request_id', 100)->nullable();
            $table->index(['zone_id', 'created_at']);
            $table->index(['riser_id', 'created_at']);
            $table->index(['operation_id', 'created_at']);
            $table->index(['request_id']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_audit_log_mutation() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
                RAISE EXCEPTION 'audit_logs is append-only';
            END;
            $$;

            CREATE TRIGGER audit_logs_append_only
            BEFORE UPDATE OR DELETE ON audit_logs
            FOR EACH ROW EXECUTE PROCEDURE prevent_audit_log_mutation();
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS audit_logs_append_only ON audit_logs');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_audit_log_mutation()');

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['request_id']);
            $table->dropIndex(['operation_id', 'created_at']);
            $table->dropIndex(['riser_id', 'created_at']);
            $table->dropIndex(['zone_id', 'created_at']);
            $table->dropConstrainedForeignId('operation_id');
            $table->dropConstrainedForeignId('riser_id');
            $table->dropConstrainedForeignId('zone_id');
            $table->dropColumn(['actor_role', 'request_id']);
        });

        Schema::table('operation_image', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('maintenance_case_id');
        });

        Schema::table('operations', function (Blueprint $table): void {
            $table->dropIndex(['group_user_id', 'operation_date']);
            $table->dropConstrainedForeignId('group_user_id');
            $table->dropConstrainedForeignId('maintenance_case_id');
        });

        Schema::dropIfExists('maintenance_cases');
    }
};
