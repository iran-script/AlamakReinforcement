<?php

namespace Tests\Feature;

use App\Models\GroupUser;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserOrganizationAssignmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_superadmin_user_forms_expose_organization_selection(): void
    {
        $this->requireSchema();
        $admin = $this->admin();
        $organization = Organization::query()->create(['code' => 'user-form-'.uniqid(), 'name' => 'User Form Org', 'status' => 'active']);

        $this->actingAs($admin)->get(route('users.create'))
            ->assertOk()->assertSee('name="organization_id"', false)->assertSee($organization->name);
    }

    public function test_superadmin_cannot_assign_user_to_group_from_another_organization(): void
    {
        $this->requireSchema();
        $admin = $this->admin();
        $organizationA = $this->organization('group-a');
        $organizationB = $this->organization('group-b');
        $group = GroupUser::query()->create(['name' => 'Group A', 'organization_id' => $organizationA->id]);

        $this->actingAs($admin)->from(route('users.create'))->post(route('users.store'), [
            'name' => 'Mismatch', 'username' => 'mismatch-'.uniqid(), 'email' => uniqid().'@example.test',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123', 'role' => 'supervisor',
            'organization_id' => $organizationB->id, 'group_user_id' => $group->id,
        ])->assertSessionHasErrors('organization_id');
    }

    public function test_superadmin_can_repair_a_null_organization_when_assignments_match(): void
    {
        $this->requireSchema();
        $admin = $this->admin();
        $organization = $this->organization('repair');
        $zoneId = (int) DB::selectOne(
            "INSERT INTO zone (name, organization_id, geom, created_at, updated_at) VALUES (?, ?, ST_MakeEnvelope(49,34,50,35,4326), now(), now()) RETURNING id",
            ['Repair zone '.uniqid(), $organization->id]
        )->id;
        $group = GroupUser::query()->create(['name' => 'Repair group', 'organization_id' => $organization->id, 'zone_id' => $zoneId]);
        $role = Role::findOrCreate('boss', 'web');
        $user = User::query()->create([
            'name' => 'Broken Boss', 'username' => 'broken-'.uniqid(), 'email' => uniqid().'@example.test',
            'password' => 'StrongPass123', 'role_id' => $role->id, 'organization_id' => null,
            'zone_id' => $zoneId, 'group_user_id' => $group->id,
        ]);
        $user->assignRole($role);

        $this->actingAs($admin)->put(route('users.update', $user), [
            'name' => $user->name, 'username' => $user->username, 'email' => $user->email,
            'role' => 'boss', 'organization_id' => $organization->id, 'zone_id' => $zoneId, 'group_user_id' => $group->id,
        ])->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', ['id' => $user->id, 'organization_id' => $organization->id]);
    }

    private function requireSchema(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('organizations') || ! Schema::hasTable('group_users')) {
            $this->markTestSkipped('User organization tests require PostgreSQL.');
        }
    }

    private function admin(): User
    {
        $role = Role::findOrCreate('superadmin', 'web');
        $user = User::query()->create([
            'name' => 'Admin', 'username' => 'admin-'.uniqid(), 'email' => uniqid().'@example.test',
            'password' => 'StrongPass123', 'role_id' => $role->id,
        ]);
        $user->assignRole($role);
        $user->givePermissionTo(Permission::findOrCreate('user.create', 'web'));

        return $user;
    }

    private function organization(string $suffix): Organization
    {
        return Organization::query()->create(['code' => 'test-'.$suffix.'-'.uniqid(), 'name' => $suffix, 'status' => 'active']);
    }
}
