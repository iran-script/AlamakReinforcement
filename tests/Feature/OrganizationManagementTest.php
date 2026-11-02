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

class OrganizationManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_non_superadmin_cannot_manage_organizations(): void
    {
        $this->requireSchema();
        $user = $this->user('operator');

        $this->actingAs($user)->get(route('organizations.index'))->assertForbidden();
        $this->actingAs($user)->post(route('organizations.store'), [
            'code' => 'blocked', 'name' => 'Blocked', 'status' => 'active',
        ])->assertForbidden();
    }

    public function test_superadmin_can_create_organization_and_assign_existing_zone(): void
    {
        $this->requireSchema();
        $admin = $this->user('superadmin');
        $zoneId = $this->zoneId();

        $this->actingAs($admin)->post(route('organizations.store'), [
            'code' => 'org-create-'.uniqid(), 'name' => 'Created Org', 'status' => 'active', 'zone_ids' => [$zoneId],
        ])->assertRedirect(route('organizations.index'));

        $organization = Organization::query()->where('name', 'Created Org')->firstOrFail();
        $this->assertDatabaseHas('zone', ['id' => $zoneId, 'organization_id' => $organization->id]);
    }

    public function test_duplicate_code_and_invalid_status_are_rejected(): void
    {
        $this->requireSchema();
        $admin = $this->user('superadmin');
        Organization::query()->create(['code' => 'duplicate-code', 'name' => 'Existing', 'status' => 'active']);

        $this->actingAs($admin)->from(route('organizations.create'))->post(route('organizations.store'), [
            'code' => 'duplicate-code', 'name' => '', 'status' => 'invalid',
        ])->assertSessionHasErrors(['code', 'name', 'status']);
    }

    public function test_organization_with_group_user_cannot_be_deleted(): void
    {
        $this->requireSchema();
        $admin = $this->user('superadmin');
        $organization = Organization::query()->create(['code' => 'org-delete-block-'.uniqid(), 'name' => 'Protected', 'status' => 'active']);
        GroupUser::query()->create(['name' => 'Protected group', 'organization_id' => $organization->id]);

        $this->actingAs($admin)->delete(route('organizations.destroy', $organization))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
    }

    private function requireSchema(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('organizations') || ! Schema::hasTable('group_users')) {
            $this->markTestSkipped('Organization schema requires PostgreSQL.');
        }
    }

    private function user(string $roleName): User
    {
        $role = Role::findOrCreate($roleName, 'web');
        $user = User::query()->create([
            'name' => ucfirst($roleName), 'username' => $roleName.'-'.uniqid(),
            'email' => $roleName.'-'.uniqid().'@example.test', 'password' => 'StrongPass123', 'role_id' => $role->id,
        ]);
        $user->assignRole($role);
        $user->givePermissionTo(Permission::findOrCreate('organization.view', 'web'));

        return $user;
    }

    private function zoneId(): int
    {
        return (int) DB::selectOne(
            "INSERT INTO zone (name, geom, created_at, updated_at) VALUES (?, ST_MakeEnvelope(49,34,50,35,4326), now(), now()) RETURNING id",
            ['organization-test-zone-'.uniqid()]
        )->id;
    }
}
