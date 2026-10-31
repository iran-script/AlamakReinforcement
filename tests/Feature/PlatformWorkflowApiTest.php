<?php

namespace Tests\Feature;

use App\Models\DataAccessPolicy;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstanceTransition;
use App\Models\WorkflowVersion;
use App\Models\WorkOrder;
use App\Models\WorkOrderType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class PlatformWorkflowApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unauthenticated_api_is_rejected(): void
    {
        $this->getJson('/api/platform/workflow-definitions')->assertUnauthorized();
    }

    public function test_definition_creation_is_isolated_and_audited(): void
    {
        $this->requireSchema();
        $organization = $this->organization('definition');
        $user = $this->user($organization);
        $this->allow($user, $organization, 'workflow_definitions', ['configure', 'view']);
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/platform/workflow-definitions', [
            'key' => 'repair', 'name' => 'Repair', 'subject_type' => 'work_orders',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('workflow_definitions', ['id' => $id, 'organization_id' => $organization->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'workflow.definition_created', 'auditable_id' => $id]);

        $foreign = WorkflowDefinition::query()->create(['organization_id' => $this->organization('foreign')->id, 'key' => 'foreign', 'name' => 'Foreign', 'subject_type' => 'work_orders']);
        $this->getJson("/api/platform/workflow-definitions/{$foreign->id}")->assertForbidden();
    }

    public function test_draft_publish_validation_valid_publish_clone_and_immutability(): void
    {
        $this->requireSchema();
        [$user, $definition] = $this->configuredUserAndDefinition('publish');
        Sanctum::actingAs($user);
        $versionId = $this->postJson("/api/platform/workflow-definitions/{$definition->id}/versions", [])->assertCreated()->json('data.id');
        $this->postJson("/api/platform/workflow-versions/{$versionId}/publish")->assertUnprocessable();

        $initial = $this->postJson("/api/platform/workflow-versions/{$versionId}/states", ['key' => 'open', 'name' => 'Open', 'category' => 'initial'])->assertCreated()->json('data.id');
        $terminal = $this->postJson("/api/platform/workflow-versions/{$versionId}/states", ['key' => 'done', 'name' => 'Done', 'category' => 'terminal'])->assertCreated()->json('data.id');
        $transition = $this->postJson("/api/platform/workflow-versions/{$versionId}/transitions", ['key' => 'finish', 'name' => 'Finish', 'from_state_id' => $initial, 'to_state_id' => $terminal])->assertCreated()->json('data.id');
        $this->postJson("/api/platform/workflow-versions/{$versionId}/publish")->assertOk()->assertJsonPath('data.status', 'published');
        $this->patchJson("/api/platform/workflow-states/{$initial}", ['name' => 'Changed'])->assertForbidden();
        $this->patchJson("/api/platform/workflow-transitions/{$transition}", ['name' => 'Changed'])->assertForbidden();
        $this->postJson("/api/platform/workflow-versions/{$versionId}/clone")->assertCreated()->assertJsonPath('data.status', 'draft');
    }

    public function test_instance_transition_links_work_order_and_history_is_immutable(): void
    {
        $this->requireSchema();
        [$user, $definition] = $this->configuredUserAndDefinition('runtime');
        $this->allow($user, $definition->organization, 'workflow_instances', ['start', 'view', 'transition']);
        $this->allow($user, $definition->organization, 'work_orders', ['view']);
        $version = $this->publishedVersion($definition, $user);
        $workOrder = $this->workOrder($definition->organization);
        Sanctum::actingAs($user);

        $instanceId = $this->postJson('/api/platform/workflows/instances', ['workflow_version_id' => $version->id, 'work_order_id' => $workOrder->id])->assertCreated()->json('data.id');
        $workOrder->refresh();
        $this->assertSame($instanceId, $workOrder->workflow_instance_id);
        $transition = $version->transitions()->first();
        $this->postJson("/api/platform/workflow-instances/{$instanceId}/transitions/{$transition->id}")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->postJson("/api/platform/workflow-instances/{$instanceId}/transitions/{$transition->id}")->assertUnprocessable();
        $history = WorkflowInstanceTransition::query()->where('workflow_instance_id', $instanceId)->firstOrFail();
        $this->expectException(LogicException::class);
        $history->update(['comment' => 'mutated']);
    }

    public function test_required_permission_denies_transition(): void
    {
        $this->requireSchema();
        [$user, $definition] = $this->configuredUserAndDefinition('permission');
        $this->allow($user, $definition->organization, 'workflow_instances', ['start', 'view', 'transition']);
        $this->allow($user, $definition->organization, 'work_orders', ['view']);
        $version = $this->publishedVersion($definition, $user, 'workflow_instances:approve');
        Sanctum::actingAs($user);
        $instance = $this->postJson('/api/platform/workflows/instances', ['workflow_version_id' => $version->id, 'work_order_id' => $this->workOrder($definition->organization)->id])->assertCreated()->json('data.id');
        $this->postJson("/api/platform/workflow-instances/{$instance}/transitions/{$version->transitions()->first()->id}")->assertUnprocessable();
    }

    private function requireSchema(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('workflow_definitions')) {
            $this->markTestSkipped('PostgreSQL workflow schema is required.');
        }
    }

    private function organization(string $key): Organization
    {
        return Organization::query()->create(['code' => $key.'-'.uniqid(), 'name' => 'Test']);
    }

    private function user(Organization $organization): User
    {
        return User::query()->create(['organization_id' => $organization->id, 'name' => 'Tester', 'username' => uniqid('workflow-'), 'password' => 'password', 'role_id' => 1]);
    }

    private function allow(User $user, Organization $organization, string $resource, array $actions): void
    {
        DataAccessPolicy::query()->create(['organization_id' => $organization->id, 'name' => uniqid('policy-'), 'principal_type' => 'user', 'principal_id' => $user->id, 'resource_key' => $resource, 'actions' => $actions, 'effect' => 'allow', 'is_active' => true]);
    }

    private function configuredUserAndDefinition(string $key): array
    {
        $organization = $this->organization($key);
        $user = $this->user($organization);
        $this->allow($user, $organization, 'workflow_definitions', ['view', 'configure', 'publish']);
        $definition = WorkflowDefinition::query()->create(['organization_id' => $organization->id, 'key' => $key, 'name' => $key, 'subject_type' => 'work_orders']);

        return [$user, $definition];
    }

    private function publishedVersion(WorkflowDefinition $definition, User $user, ?string $permission = null): WorkflowVersion
    {
        $version = $definition->versions()->create(['version' => 1, 'status' => 'draft']);
        $initial = $version->states()->create(['key' => 'open', 'name' => 'Open', 'category' => 'initial']);
        $terminal = $version->states()->create(['key' => 'done', 'name' => 'Done', 'category' => 'terminal']);
        $version->transitions()->create(['key' => 'finish', 'name' => 'Finish', 'from_state_id' => $initial->id, 'to_state_id' => $terminal->id, 'required_permission' => $permission]);
        $version->update(['status' => 'published', 'published_at' => now(), 'published_by' => $user->id]);

        return $version->refresh();
    }

    private function workOrder(Organization $organization): WorkOrder
    {
        $type = WorkOrderType::query()->create(['organization_id' => $organization->id, 'key' => uniqid('type-'), 'name' => 'Type']);

        return WorkOrder::query()->create(['organization_id' => $organization->id, 'work_order_type_id' => $type->id, 'title' => 'Work', 'status' => 'draft', 'priority' => 'normal']);
    }
}
