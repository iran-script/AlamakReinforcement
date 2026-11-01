<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_users', function (Blueprint $table): void {
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zone')->nullOnDelete();
            $table->index(['organization_id', 'zone_id']);
        });

        DB::statement(<<<'SQL'
            WITH unique_group_scope AS (
                SELECT group_user_id,
                       MIN(organization_id) AS organization_id,
                       MIN(zone_id) AS zone_id
                FROM users
                WHERE group_user_id IS NOT NULL
                GROUP BY group_user_id
                HAVING COUNT(*) = COUNT(organization_id)
                   AND COUNT(*) = COUNT(zone_id)
                   AND COUNT(DISTINCT organization_id) = 1
                   AND COUNT(DISTINCT zone_id) = 1
            )
            UPDATE group_users AS groups
               SET organization_id = scope.organization_id,
                   zone_id = scope.zone_id
              FROM unique_group_scope AS scope
             WHERE groups.id = scope.group_user_id
        SQL);

        Schema::create('boss_managed_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('boss_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_user_id')->constrained('group_users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['boss_user_id', 'group_user_id']);
            $table->index(['group_user_id', 'boss_user_id']);
        });

        DB::statement(<<<'SQL'
            INSERT INTO boss_managed_groups (boss_user_id, group_user_id, assigned_by, created_at, updated_at)
            SELECT DISTINCT boss.id, groups.id, boss.id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
              FROM users AS boss
              JOIN model_has_roles AS assignment
                ON assignment.model_id = boss.id
               AND assignment.model_type = 'App\Models\User'
              JOIN roles ON roles.id = assignment.role_id
               AND roles.name = 'boss'
               AND roles.guard_name = 'web'
              JOIN group_users AS groups ON groups.id = boss.group_user_id
               AND groups.organization_id = boss.organization_id
               AND groups.zone_id = boss.zone_id
             WHERE boss.group_user_id IS NOT NULL
        SQL);

        Schema::table('maintenance_cases', function (Blueprint $table): void {
            $table->foreignId('last_edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->index(['status', 'identified_at']);
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_cases', function (Blueprint $table): void {
            $table->dropIndex(['status', 'identified_at']);
            $table->dropConstrainedForeignId('last_edited_by');
            $table->dropColumn('submitted_at');
        });

        Schema::dropIfExists('boss_managed_groups');

        Schema::table('group_users', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'zone_id']);
            $table->dropConstrainedForeignId('organization_id');
            $table->dropConstrainedForeignId('zone_id');
        });
    }
};
