<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key', 80);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('resource_key', 100)->nullable();
            $table->string('subject_type', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['organization_id', 'key'], 'form_definitions_org_key_unique');
            $table->index(['organization_id', 'resource_key', 'is_active'], 'form_definitions_lookup_index');
        });
        DB::statement('CREATE UNIQUE INDEX form_definitions_global_key_unique ON form_definitions (key) WHERE organization_id IS NULL');

        Schema::create('form_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_definition_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 20)->default('draft');
            $table->jsonb('schema')->nullable();
            $table->jsonb('ui_schema')->nullable();
            $table->jsonb('configuration')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['form_definition_id', 'version']);
            $table->index(['form_definition_id', 'status']);
        });
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_version_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('field_type', 40);
            $table->string('data_type', 40)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('placeholder')->nullable();
            foreach (['default_value', 'options', 'validation', 'visibility_condition', 'required_condition', 'access_definition', 'configuration'] as $column) {
                $table->jsonb($column)->nullable();
            }
            $table->boolean('is_required')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['form_version_id', 'key']);
            $table->index(['form_version_id', 'is_active', 'sort_order'], 'form_fields_order_index');
        });
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('form_version_id')->constrained()->restrictOnDelete();
            $table->string('subject_type', 100)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->foreignId('workflow_instance_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_transition_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->jsonb('data')->nullable();
            $table->jsonb('context')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['workflow_instance_id', 'workflow_transition_id'], 'form_submissions_workflow_index');
        });
        Schema::create('form_submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_submission_id')->constrained()->cascadeOnDelete();
            $table->string('field_key', 80);
            $table->string('disk', 40);
            $table->string('path', 1024);
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
            $table->index(['form_submission_id', 'field_key']);
        });
        DB::statement("CREATE OR REPLACE FUNCTION prevent_submitted_form_mutation() RETURNS trigger AS $$ BEGIN IF OLD.status IN ('submitted','superseded','cancelled') THEN RAISE EXCEPTION 'final form submissions are immutable'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
        DB::statement('CREATE TRIGGER submitted_form_immutable BEFORE UPDATE OR DELETE ON form_submissions FOR EACH ROW EXECUTE PROCEDURE prevent_submitted_form_mutation()');
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submission_files');
        DB::statement('DROP TRIGGER IF EXISTS submitted_form_immutable ON form_submissions');
        DB::statement('DROP FUNCTION IF EXISTS prevent_submitted_form_mutation()');
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('form_versions');
        Schema::dropIfExists('form_definitions');
    }
};
