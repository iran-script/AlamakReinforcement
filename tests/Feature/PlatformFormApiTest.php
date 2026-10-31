<?php

namespace Tests\Feature;

use App\Models\DataAccessPolicy;
use App\Models\FormDefinition;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformFormApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_form_api_rejects_unauthenticated_requests(): void
    {
        $this->getJson('/api/platform/form-definitions')->assertUnauthorized();
        $this->postJson('/api/platform/form-submissions', [])->assertUnauthorized();
        $this->getJson('/api/platform/forms/resolve?form_key=inspection')->assertUnauthorized();
    }

    public function test_definition_draft_field_publish_clone_and_audit_lifecycle(): void
    {
        $this->requireSchema();
        [$organization, $user] = $this->actor('lifecycle');
        Sanctum::actingAs($user);

        $definition = $this->postJson('/api/platform/form-definitions', ['key' => 'inspection', 'name' => 'Inspection', 'resource_key' => 'work_orders'])
            ->assertCreated()->json('data.id');
        $version = $this->postJson("/api/platform/form-definitions/{$definition}/versions", [])->assertCreated()->json('data.id');
        $this->postJson("/api/platform/form-versions/{$version}/publish")->assertUnprocessable();
        $this->postJson("/api/platform/form-versions/{$version}/fields", ['key' => 'result', 'label' => 'Result', 'field_type' => 'unsupported'])->assertUnprocessable();
        $this->postJson("/api/platform/form-versions/{$version}/fields", ['key' => 'result', 'label' => 'Result', 'field_type' => 'select', 'options' => []])->assertUnprocessable();
        $field = $this->postJson("/api/platform/form-versions/{$version}/fields", ['key' => 'result', 'label' => 'Result', 'field_type' => 'select', 'is_required' => true, 'options' => [['value' => 'ok', 'label' => 'OK']]])->assertCreated()->json('data.id');
        $this->postJson("/api/platform/form-versions/{$version}/publish")->assertOk()->assertJsonPath('data.status', 'published');
        $this->patchJson("/api/platform/form-fields/{$field}", ['label' => 'Changed'])->assertForbidden();
        $this->postJson("/api/platform/form-versions/{$version}/clone")->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'event' => 'form.version_published']);
    }

    public function test_submission_validation_field_access_and_immutability(): void
    {
        $this->requireSchema();
        [, $user] = $this->actor('submission');
        $definition = FormDefinition::create(['organization_id' => $user->organization_id, 'key' => 'submission', 'name' => 'Submission']);
        $version = $definition->versions()->create(['version' => 1, 'status' => 'published', 'published_at' => now(), 'published_by' => $user->id]);
        $version->fields()->create(['key' => 'required_value', 'label' => 'Required', 'field_type' => 'text', 'is_required' => true]);
        $version->fields()->create(['key' => 'secret', 'label' => 'Secret', 'field_type' => 'text', 'access_definition' => ['default' => 'hidden']]);
        Sanctum::actingAs($user);
        $submission = $this->postJson('/api/platform/form-submissions', ['form_version_id' => $version->id])->assertCreated()->json('data.id');
        $this->patchJson("/api/platform/form-submissions/{$submission}/draft", ['data' => ['secret' => 'x']])->assertUnprocessable();
        $this->postJson("/api/platform/form-submissions/{$submission}/submit", ['data' => []])->assertUnprocessable();
        $this->patchJson("/api/platform/form-submissions/{$submission}/draft", ['data' => ['required_value' => 'ok']])->assertOk();
        $this->postJson("/api/platform/form-submissions/{$submission}/submit", ['data' => ['required_value' => 'ok']])->assertOk()->assertJsonPath('data.status', 'submitted');
        $this->patchJson("/api/platform/form-submissions/{$submission}/draft", ['data' => ['required_value' => 'changed']])->assertForbidden();
    }

    public function test_organization_specific_resolution_wins_and_foreign_definition_is_forbidden(): void
    {
        $this->requireSchema();
        [$organization, $user] = $this->actor('resolution');
        $foreign = Organization::create(['code' => uniqid('foreign-'), 'name' => 'Foreign']);
        $foreignDefinition = FormDefinition::create(['organization_id' => $foreign->id, 'key' => 'private', 'name' => 'Private']);
        $global = FormDefinition::create(['key' => 'shared', 'name' => 'Global']);
        $globalVersion = $global->versions()->create(['version' => 1, 'status' => 'published', 'published_at' => now()]);
        $globalVersion->fields()->create(['key' => 'global_field', 'label' => 'Global', 'field_type' => 'text']);
        $local = FormDefinition::create(['organization_id' => $organization->id, 'key' => 'shared', 'name' => 'Local']);
        $localVersion = $local->versions()->create(['version' => 1, 'status' => 'published', 'published_at' => now()]);
        $localVersion->fields()->create(['key' => 'local_field', 'label' => 'Local', 'field_type' => 'text']);
        Sanctum::actingAs($user);
        $this->getJson("/api/platform/form-definitions/{$foreignDefinition->id}")->assertForbidden();
        $this->getJson('/api/platform/forms/resolve?form_key=shared')->assertOk()->assertJsonPath('data.definition.name', 'Local')->assertJsonPath('data.fields.0.key', 'local_field');
    }

    private function requireSchema(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('form_definitions')) {
            $this->markTestSkipped('PostgreSQL form schema is required.');
        }
    }

    private function actor(string $key): array
    {
        $organization = Organization::create(['code' => $key.'-'.uniqid(), 'name' => 'Test']);
        $user = User::create(['organization_id' => $organization->id, 'name' => 'Tester', 'username' => uniqid('form-'), 'password' => 'password', 'role_id' => 1]);
        foreach ([['form_definitions', ['view', 'configure', 'publish']], ['form_submissions', ['view', 'submit', 'upload']]] as [$resource, $actions]) {
            DataAccessPolicy::create(['organization_id' => $organization->id, 'name' => uniqid('policy-'), 'principal_type' => 'user', 'principal_id' => $user->id, 'resource_key' => $resource, 'actions' => $actions, 'effect' => 'allow', 'is_active' => true]);
        }

        return [$organization, $user];
    }
}
