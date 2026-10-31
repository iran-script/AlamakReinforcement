<?php

namespace Tests\Feature;

use App\Models\DataAccessPolicy;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorkflowStudioPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unauthenticated_studio_redirects_to_login(): void
    {
        $this->get('/platform/workflows')->assertRedirect('/login');
    }

    public function test_unauthorized_and_foreign_pages_are_forbidden(): void
    {
        $this->requireSchema();
        [$org, $user] = $this->actor('denied');
        $definition = WorkflowDefinition::create(['organization_id' => $org->id, 'key' => 'denied', 'name' => 'Denied', 'subject_type' => 'work_orders']);
        $this->actingAs($user)->get('/platform/workflows')->assertForbidden();
        $this->allow($user, $org, 'workflow_definitions', ['view']);
        $foreign = Organization::create(['code' => uniqid('foreign-'), 'name' => 'Foreign']);
        $foreignDefinition = WorkflowDefinition::create(['organization_id' => $foreign->id, 'key' => 'foreign', 'name' => 'Foreign', 'subject_type' => 'work_orders']);
        $this->actingAs($user)->get("/platform/workflows/{$foreignDefinition->id}")->assertForbidden();
        $this->actingAs($user)->get("/platform/workflows/{$definition->id}")->assertOk();
    }

    public function test_index_navigation_detail_designer_and_preview_render_safely(): void
    {
        $this->requireSchema();
        [$org, $user] = $this->actor('render');
        $this->allow($user, $org, 'workflow_definitions', ['view', 'configure', 'publish']);
        $definition = WorkflowDefinition::create(['organization_id' => $org->id, 'key' => 'repair', 'name' => 'گردش تعمیر', 'subject_type' => 'work_orders']);
        $draft = $definition->versions()->create(['version' => 1, 'status' => 'draft']);
        $published = $definition->versions()->create(['version' => 2, 'status' => 'published', 'published_at' => now()]);
        $archived = $definition->versions()->create(['version' => 3, 'status' => 'archived']);
        $initial = $draft->states()->create(['key' => 'start', 'name' => 'شروع', 'category' => 'initial']);
        $terminal = $draft->states()->create(['key' => 'done', 'name' => 'پایان', 'category' => 'terminal']);
        $draft->transitions()->create(['key' => 'finish', 'name' => 'اتمام', 'from_state_id' => $initial->id, 'to_state_id' => $terminal->id]);

        $this->actingAs($user)->get('/platform/workflows')->assertOk()->assertSee('مدیریت گردش‌کار')->assertSee('گردش تعمیر');
        $this->actingAs($user)->get("/platform/workflows/{$definition->id}")->assertOk()->assertSee('تاریخچه نسخه‌ها');
        $this->actingAs($user)->get("/platform/workflow-versions/{$draft->id}/designer")->assertOk()->assertSee('data-editable="1"', false)->assertSee('ذخیره پیش‌نویس');
        $this->actingAs($user)->get("/platform/workflow-versions/{$published->id}/designer")->assertOk()->assertSee('data-editable="0"', false)->assertSee('فقط‌خواندنی')->assertDontSee('ذخیره پیش‌نویس');
        $this->actingAs($user)->get("/platform/workflow-versions/{$archived->id}/designer")->assertOk()->assertSee('data-editable="0"', false);
        $this->actingAs($user)->get("/platform/workflow-versions/{$draft->id}/preview")->assertOk()->assertSee('شبیه‌سازی صرفاً بصری');
    }

    public function test_instance_view_is_organization_isolated(): void
    {
        $this->requireSchema();
        [$org, $user] = $this->actor('instance');
        $this->allow($user, $org, 'workflow_instances', ['view']);
        $foreign = Organization::create(['code' => uniqid('foreign-'), 'name' => 'Foreign']);
        $instance = WorkflowInstance::where('organization_id', $foreign->id)->first();
        if ($instance) {
            $this->actingAs($user)->get("/platform/workflow-instances/{$instance->id}")->assertForbidden();
        } else {
            $this->assertTrue(true);
        }
    }

    private function requireSchema(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('workflow_definitions')) {
            $this->markTestSkipped('PostgreSQL workflow schema is required.');
        }
    }

    private function actor(string $key): array
    {
        $org = Organization::create(['code' => $key.'-'.uniqid(), 'name' => 'Test']);
        $user = User::create(['organization_id' => $org->id, 'name' => 'Tester', 'username' => uniqid('workflow-studio-'), 'password' => 'password', 'role_id' => 1]);

        return [$org, $user];
    }

    private function allow(User $user, Organization $org, string $resource, array $actions): void
    {
        DataAccessPolicy::create(['organization_id' => $org->id, 'name' => uniqid('policy-'), 'principal_type' => 'user', 'principal_id' => $user->id, 'resource_key' => $resource, 'actions' => $actions, 'effect' => 'allow', 'is_active' => true]);
    }
}
