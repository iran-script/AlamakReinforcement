<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SupervisorMobileApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_only_supervisors_can_obtain_a_mobile_token(): void
    {
        $supervisor = $this->user('mobile-supervisor', 'supervisor');
        $boss = $this->user('mobile-boss', 'boss');
        $admin = $this->user('mobile-admin', 'superadmin');

        $response = $this->postJson('/api/login', ['username' => $supervisor->username, 'password' => 'secret-pass']);
        $response->assertOk()->assertJsonPath('user.role', 'supervisor')->assertJsonMissingPath('user.password');
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $supervisor->id, 'name' => 'alamak-supervisor-app']);
        $this->withToken($response->json('token'))->getJson('/api/v1/supervisor/me')
            ->assertOk()->assertJsonPath('user.id', $supervisor->id)->assertJsonPath('user.role', 'supervisor');

        $this->postJson('/api/login', ['username' => $boss->username, 'password' => 'secret-pass'])->assertUnauthorized();
        $this->postJson('/api/login', ['username' => $admin->username, 'password' => 'secret-pass'])->assertUnauthorized();
    }

    public function test_boss_and_superadmin_are_rejected_from_supervisor_api_even_when_authenticated(): void
    {
        $boss = $this->user('api-boss', 'boss');
        $admin = $this->user('api-superadmin', 'superadmin');
        $this->actingAs($boss)->getJson('/api/v1/supervisor/me')->assertForbidden();
        $this->actingAs($admin)->getJson('/api/v1/supervisor/me')->assertForbidden();
    }

    public function test_supervisor_token_with_wrong_ability_is_rejected(): void
    {
        $supervisor = $this->user('wrong-token', 'supervisor');
        $token = $supervisor->createToken('wrong-scope', ['map.view'])->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/supervisor/me')->assertForbidden();
    }

    public function test_mobile_riser_queries_require_a_small_complete_viewport(): void
    {
        $supervisor = $this->user('bounded-mobile-map', 'supervisor');
        $token = $supervisor->createToken('bounded-map', ['mobile:supervisor'])->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/supervisor/risers?bbox_west=-10&bbox_south=35&bbox_east=10&bbox_north=36')
            ->assertUnprocessable();
        $this->withToken($token)->getJson('/api/v1/supervisor/risers?bbox_west=51&bbox_south=35')
            ->assertUnprocessable();
    }

    private function user(string $key, string $role): User
    {
        $organization = Organization::create(['code' => $key.'-'.uniqid(), 'name' => 'Mobile API Test']);
        $user = User::create([
            'organization_id' => $organization->id,
            'name' => $key,
            'username' => $key.'-'.uniqid(),
            'password' => 'secret-pass',
            'role_id' => 1,
        ]);
        $user->assignRole(Role::findOrCreate($role, 'web'));
        return $user;
    }
}
