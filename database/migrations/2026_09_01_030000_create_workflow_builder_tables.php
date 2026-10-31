<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('subject_type', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'key'], 'workflow_definitions_org_key_unique');
            $table->index(['organization_id', 'subject_type', 'is_active'], 'workflow_definitions_lookup_index');
        });
        DB::statement('CREATE UNIQUE INDEX workflow_definitions_global_key_unique ON workflow_definitions (key) WHERE organization_id IS NULL');

        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_definition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft');
            $table->jsonb('configuration')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['workflow_definition_id', 'version']);
            $table->index(['workflow_definition_id', 'status']);
        });

        Schema::create('workflow_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category', 20)->default('intermediate');
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('configuration')->nullable();
            $table->timestamps();
            $table->unique(['workflow_version_id', 'key']);
            $table->unique(['id', 'workflow_version_id'], 'workflow_states_id_version_unique');
            $table->index(['workflow_version_id', 'category', 'sort_order'], 'workflow_states_order_index');
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_version_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('name');
            $table->unsignedBigInteger('from_state_id')->nullable();
            $table->unsignedBigInteger('to_state_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->jsonb('condition_definition')->nullable();
            $table->jsonb('assignment_definition')->nullable();
            $table->string('required_permission', 120)->nullable();
            $table->string('required_form_key', 120)->nullable();
            $table->jsonb('configuration')->nullable();
            $table->timestamps();
            $table->unique(['workflow_version_id', 'key']);
            $table->index(['workflow_version_id', 'from_state_id', 'sort_order'], 'workflow_transitions_source_index');
        });
        DB::statement('ALTER TABLE workflow_transitions ADD CONSTRAINT workflow_transitions_from_state_version_foreign FOREIGN KEY (from_state_id, workflow_version_id) REFERENCES workflow_states (id, workflow_version_id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE workflow_transitions ADD CONSTRAINT workflow_transitions_to_state_version_foreign FOREIGN KEY (to_state_id, workflow_version_id) REFERENCES workflow_states (id, workflow_version_id) ON DELETE CASCADE');

        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workflow_version_id')->constrained()->restrictOnDelete();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('current_state_id');
            $table->string('status', 20)->default('active');
            $table->jsonb('context')->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id'], 'workflow_instances_subject_index');
            $table->index(['organization_id', 'status', 'current_state_id'], 'workflow_instances_active_index');
            $table->index(['workflow_version_id', 'status']);
        });
        DB::statement('ALTER TABLE workflow_instances ADD CONSTRAINT workflow_instances_state_version_foreign FOREIGN KEY (current_state_id, workflow_version_id) REFERENCES workflow_states (id, workflow_version_id) ON DELETE RESTRICT');
        DB::statement("CREATE UNIQUE INDEX workflow_instances_active_subject_unique ON workflow_instances (workflow_version_id, subject_type, subject_id) WHERE status = 'active'");

        Schema::create('workflow_instance_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_transition_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_state_id')->nullable()->constrained('workflow_states')->restrictOnDelete();
            $table->foreignId('to_state_id')->constrained('workflow_states')->restrictOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->jsonb('input')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('performed_at');
            $table->timestamps();
            $table->index(['workflow_instance_id', 'performed_at'], 'workflow_history_order_index');
        });
        DB::statement("CREATE FUNCTION prevent_workflow_history_mutation() RETURNS trigger AS $$ BEGIN RAISE EXCEPTION 'workflow instance transition history is immutable'; END; $$ LANGUAGE plpgsql");
        DB::statement('CREATE TRIGGER workflow_history_immutable BEFORE UPDATE OR DELETE ON workflow_instance_transitions FOR EACH ROW EXECUTE PROCEDURE prevent_workflow_history_mutation()');

        Schema::create('workflow_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_instance_transition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('status', 20)->default('open');
            $table->string('title');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status', 'assigned_to'], 'workflow_tasks_user_inbox_index');
            $table->index(['organization_id', 'status', 'assigned_role_id'], 'workflow_tasks_role_inbox_index');
            $table->index(['workflow_instance_id', 'status']);
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->foreignId('workflow_definition_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workflow_version_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workflow_instance_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['organization_id', 'workflow_definition_id']);
            $table->index(['workflow_instance_id']);
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['workflow_definition_id']);
            $table->dropForeign(['workflow_version_id']);
            $table->dropForeign(['workflow_instance_id']);
            $table->dropColumn(['workflow_definition_id', 'workflow_version_id', 'workflow_instance_id']);
        });
        Schema::dropIfExists('workflow_tasks');
        Schema::dropIfExists('workflow_instance_transitions');
        DB::statement('DROP FUNCTION IF EXISTS prevent_workflow_history_mutation()');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_states');
        Schema::dropIfExists('workflow_versions');
        Schema::dropIfExists('workflow_definitions');
    }
};
