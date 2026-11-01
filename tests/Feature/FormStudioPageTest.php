<?php

namespace Tests\Feature;

use App\Models\DataAccessPolicy;
use App\Models\FormDefinition;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FormStudioPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unauthenticated_page_redirects_to_login(): void
    {
        $this->get('/platform/forms')->assertRedirect('/login');
    }

    public function test_unauthorized_user_cannot_access_studio(): void
    {
        $this->requireSchema();
        [$organization, $user] = $this->actor('denied');
        $definition = FormDefinition::create(['organization_id' => $organization->id, 'key' => 'denied', 'name' => 'Denied']);
        $this->actingAs($user)->get('/platform/forms')->assertForbidden();
        $this->actingAs($user)->get("/platform/forms/{$definition->id}")->assertForbidden();
    }

    public function test_authorized_pages_render_and_foreign_definition_is_isolated(): void
    {
        $this->requireSchema();
        [$organization, $user] = $this->actor('allowed');
        $this->allow($user, $organization, ['view', 'configure', 'publish']);
        $definition = FormDefinition::create(['organization_id' => $organization->id, 'key' => 'inspection', 'name' => 'بازرسی']);
        $foreign = Organization::create(['code' => uniqid('foreign-'), 'name' => 'Foreign']);
        $foreignDefinition = FormDefinition::create(['organization_id' => $foreign->id, 'key' => 'foreign', 'name' => 'Foreign']);

        $this->actingAs($user)->get('/platform/forms')->assertOk()->assertSee('مدیریت فرم‌ها')->assertSee('بازرسی')->assertSee('FORM STUDIO');
        $this->actingAs($user)->get("/platform/forms/{$definition->id}")->assertOk();
        $this->actingAs($user)->get("/platform/forms/{$foreignDefinition->id}")->assertForbidden();
    }

    public function test_draft_designer_is_editable_and_published_designer_is_readonly(): void
    {
        $this->requireSchema();
        [$organization, $user] = $this->actor('designer');
        $this->allow($user, $organization, ['view', 'configure', 'publish']);
        $definition = FormDefinition::create(['organization_id' => $organization->id, 'key' => 'designer', 'name' => 'Designer']);
        $draft = $definition->versions()->create(['version' => 1, 'status' => 'draft']);
        $published = $definition->versions()->create(['version' => 2, 'status' => 'published', 'published_at' => now()]);

        $this->actingAs($user)->get("/platform/form-versions/{$draft->id}/designer")->assertOk()->assertSee('data-editable="1"', false)->assertSee('ذخیره پیش‌نویس');
        $this->actingAs($user)->get("/platform/form-versions/{$published->id}/designer")->assertOk()->assertSee('data-editable="0"', false)->assertSee('حالت فقط‌خواندنی')->assertDontSee('ذخیره پیش‌نویس');
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
        $user = User::create(['organization_id' => $organization->id, 'name' => 'Tester', 'username' => uniqid('studio-'), 'password' => 'password', 'role_id' => 1]);

        return [$organization, $user];
    }

    private function allow(User $user, Organization $organization, array $actions): void
    {
        DataAccessPolicy::create(['organization_id' => $organization->id, 'name' => uniqid('policy-'), 'principal_type' => 'user', 'principal_id' => $user->id, 'resource_key' => 'form_definitions', 'actions' => $actions, 'effect' => 'allow', 'is_active' => true]);
    }
}
