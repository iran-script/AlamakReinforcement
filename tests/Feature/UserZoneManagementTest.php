<?php

namespace Tests\Feature;

use App\Models\GroupUser;
use App\Models\Menu;
use App\Models\Organization;
use App\Models\User;
use App\Models\Zone;
use App\Services\MenuService;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserZoneManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_superadmin_can_create_user_with_web_role_and_single_hash(): void
    {
        $admin = $this->actor('superadmin');
        $role = Role::findOrCreate('supervisor', 'web');

        $this->actingAs($admin)->post(route('users.store'), $this->userPayload('created-by-admin', 'supervisor'))
            ->assertRedirect(route('users.index'));

        $created = User::query()->where('username', 'created-by-admin')->firstOrFail();
        $this->assertTrue(Hash::check('StrongPass123', $created->password));
        $this->assertSame($role->id, $created->role_id);
        $this->assertTrue($created->hasRole('supervisor'));
        $this->assertSame('web', $created->roles()->first()->guard_name);
    }

    public function test_superadmin_partial_update_preserves_unsubmitted_scope_fields(): void
    {
        $admin = $this->actor('superadmin');
        $organization = $this->organization('admin-update-preserve');
        $group = $this->group('admin-update-preserve');
        $target = $this->actor('supervisor', $organization->id, $group->id);

        $this->actingAs($admin)->put(route('users.update', $target), $this->userPayload($target->username, 'supervisor'))
            ->assertRedirect(route('users.index'));

        $target->refresh();
        $this->assertSame($organization->id, $target->organization_id);
        $this->assertSame($group->id, $target->group_user_id);
    }

    public function test_boss_creates_only_in_own_group_and_zone_and_cannot_forge_scope_fields(): void
    {
        [$organization, $foreignOrganization] = $this->organizations();
        [$group, $foreignGroup] = $this->createGroups();
        $boss = $this->actor('boss', $organization->id, $group->id);
        $ownZone = $this->zone($boss, $organization->id, 'boss-own-zone');
        $this->zone($this->actor('boss', $foreignOrganization->id, $foreignGroup->id), $foreignOrganization->id, 'foreign-zone');

        $payload = $this->userPayload('created-by-boss', 'supervisor') + [
            'group_user_id' => $group->id,
            'zone_id' => $ownZone->id,
            'organization_id' => $foreignOrganization->id,
        ];

        $this->actingAs($boss)->post(route('users.store'), $payload)
            ->assertRedirect(route('users.index'));

        $created = User::query()->where('username', 'created-by-boss')->firstOrFail();
        $this->assertSame($group->id, $created->group_user_id);
        $this->assertSame($ownZone->id, $created->zone_id);
        $this->assertSame($organization->id, $created->organization_id);
        $this->assertTrue($created->hasRole('supervisor'));
        $this->assertFalse($created->hasRole('boss'));
        $this->assertFalse($created->hasRole('superadmin'));
    }

    public function test_boss_cannot_assign_boss_or_superadmin_role(): void
    {
        $organization = $this->organization('boss-role-allowlist');
        $group = $this->group('boss-role-allowlist');
        $boss = $this->actor('boss', $organization->id, $group->id);
        $this->zone($boss, $organization->id, 'role-allowlist-zone');
        Role::findOrCreate('supervisor', 'web');

        foreach (['boss', 'superadmin'] as $role) {
            $this->actingAs($boss)->from(route('users.create'))
                ->post(route('users.store'), $this->userPayload('forbidden-'.$role, $role))
                ->assertSessionHasErrors('role');
        }
    }

    public function test_boss_cannot_create_a_user_in_a_foreign_zone(): void
    {
        [$organization, $foreignOrganization] = $this->organizations();
        [$group, $foreignGroup] = $this->createGroups();
        $boss = $this->actor('boss', $organization->id, $group->id);
        $this->zone($boss, $organization->id, 'boss-zone-for-foreign-attempt');
        $foreignZone = $this->zone($this->actor('boss', $foreignOrganization->id, $foreignGroup->id), $foreignOrganization->id, 'other-boss-zone');

        $this->actingAs($boss)->post(route('users.store'), $this->userPayload('foreign-zone-user', 'supervisor') + [
            'zone_id' => $foreignZone->id,
            'group_user_id' => $foreignGroup->id,
            'organization_id' => $foreignOrganization->id,
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['username' => 'foreign-zone-user']);
    }

    public function test_boss_list_and_edit_are_limited_to_group_and_owned_zone(): void
    {
        [$organization, $otherOrganization] = $this->organizations();
        [$group, $otherGroup] = $this->createGroups();
        $boss = $this->actor('boss', $organization->id, $group->id);
        $ownZone = $this->zone($boss, $organization->id, 'boss-visible-zone');
        $otherBoss = $this->actor('boss', $otherOrganization->id, $otherGroup->id);
        $otherZone = $this->zone($otherBoss, $otherOrganization->id, 'boss-hidden-zone');
        $inScope = $this->actor('supervisor', $organization->id, $group->id, $ownZone->id);
        $outOfScope = $this->actor('supervisor', $organization->id, $group->id, $otherZone->id);
        $bossPeer = $this->actor('boss', $organization->id, $group->id, $ownZone->id);
        $superadminPeer = $this->actor('superadmin', $organization->id, $group->id, $ownZone->id);

        $response = $this->actingAs($boss)->get(route('users.index'))->assertOk();
        $response->assertSee($inScope->username)
            ->assertDontSee($outOfScope->username)
            ->assertDontSee($bossPeer->username)
            ->assertDontSee($superadminPeer->username);
        $this->actingAs($boss)->get(route('users.edit', $outOfScope))->assertForbidden();
    }

    public function test_boss_cannot_change_own_role_or_change_supervisor_scope_to_foreign_zone(): void
    {
        [$organization, $otherOrganization] = $this->organizations();
        [$group, $otherGroup] = $this->createGroups();
        $boss = $this->actor('boss', $organization->id, $group->id);
        $ownZone = $this->zone($boss, $organization->id, 'boss-zone-update');
        $otherZone = $this->zone($this->actor('boss', $otherOrganization->id, $otherGroup->id), $otherOrganization->id, 'other-zone-update');
        $supervisor = $this->actor('supervisor', $organization->id, $group->id, $ownZone->id);
        Role::findOrCreate('superadmin', 'web');

        $selfPayload = $this->userPayload($boss->username, 'supervisor');
        $this->actingAs($boss)->put(route('users.update', $boss), $selfPayload)->assertForbidden();

        $this->actingAs($boss)->put(route('users.update', $supervisor), $this->userPayload($supervisor->username, 'supervisor') + [
            'zone_id' => $otherZone->id,
            'group_user_id' => $otherGroup->id,
            'organization_id' => $otherOrganization->id,
        ])->assertForbidden();
    }

    public function test_boss_and_superadmin_see_user_and_zone_links_without_duplicates(): void
    {
        $organization = $this->organization('sidebar');
        $group = $this->group('sidebar');
        $boss = $this->actor('boss', $organization->id, $group->id);
        $this->zone($boss, $organization->id, 'sidebar-boss-zone');
        app(MenuSeeder::class)->run();
        $this->actingAs($boss);

        $html = view('components.sidebar', ['menus' => app(MenuService::class)->get()])->render();
        $this->assertSame(1, substr_count($html, 'href="'.route('users.index').'"'));
        $this->assertSame(1, substr_count($html, 'href="'.route('zones.manage').'"'));

        $customGroup = Menu::query()->create([
            'title' => 'گروه سفارشی', 'route' => '__group:custom-duplicate',
            'sort' => 0, 'visible' => 1, 'is_active' => true,
        ]);
        foreach ([['users.index', 'کاربران'], ['zones.manage', 'ناحیه‌ها']] as [$route, $title]) {
            $customGroup->children()->create([
                'title' => $title, 'route' => $route, 'sort' => 1,
                'visible' => 1, 'is_active' => true,
            ]);
        }

        $htmlWithDatabaseMenus = view('components.sidebar', ['menus' => app(MenuService::class)->get()])->render();
        $this->assertSame(1, substr_count($htmlWithDatabaseMenus, 'href="'.route('users.index').'"'));
        $this->assertSame(1, substr_count($htmlWithDatabaseMenus, 'href="'.route('zones.manage').'"'));
    }

    public function test_user_without_management_permissions_cannot_open_routes_or_see_sidebar_links(): void
    {
        $user = $this->actor('operator');
        app(MenuSeeder::class)->run();
        $this->actingAs($user);

        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('zones.manage'))->assertForbidden();
        $html = view('components.sidebar', ['menus' => app(MenuService::class)->get()])->render();
        $this->assertStringNotContainsString('href="'.route('users.index').'"', $html);
        $this->assertStringNotContainsString('href="'.route('zones.manage').'"', $html);
    }

    public function test_boss_cannot_delete_users_and_required_zone_cannot_be_omitted(): void
    {
        $organization = $this->organization('delete-denied');
        $group = $this->group('delete-denied');
        $boss = $this->actor('boss', $organization->id, $group->id);
        $ownZone = $this->zone($boss, $organization->id, 'boss-delete-denied');
        $supervisor = $this->actor('supervisor', $organization->id, $group->id, $ownZone->id);

        $this->actingAs($boss)->delete(route('users.destroy', $supervisor))->assertForbidden();
        $this->actingAs($boss)->from(route('users.create'))
            ->post(route('users.store'), $this->userPayload('missing-zone', 'supervisor'))
            ->assertSessionHasErrors('zone_id');
    }

    public function test_zone_list_is_owner_scoped_and_boss_cannot_create_edit_or_delete_zone(): void
    {
        [$organization, $otherOrganization] = $this->organizations();
        $group = $this->group('zone-owner-test');
        $boss = $this->actor('boss', $organization->id, $group->id);
        $ownZone = $this->zone($boss, $organization->id, 'zone-owner-visible');
        $otherZone = $this->zone($this->actor('boss', $otherOrganization->id, $this->group('zone-other-test')->id), $otherOrganization->id, 'zone-owner-hidden');
        $childName = 'boss-child-visible-'.uniqid();
        $this->actingAs($boss)->postJson(route('zones.store'), [
            'name' => $childName,
            'geometry' => $this->validZonePayload('child')['geometry'],
        ])->assertOk();
        $childZone = Zone::query()->where('name', $childName)->firstOrFail();

        $this->actingAs($boss)->get(route('zones.index'))
            ->assertOk()
            ->assertJsonCount(1, 'features')
            ->assertJsonFragment(['id' => $childZone->id, 'name' => $childZone->name])
            ->assertJsonMissing(['id' => $ownZone->id, 'name' => $ownZone->name])
            ->assertJsonMissing(['id' => $otherZone->id, 'name' => $otherZone->name]);

        $this->actingAs($boss)->put(route('zones.update', $ownZone), $this->validZonePayload('boss-zone-edit-denied'))->assertForbidden();
        $this->actingAs($boss)->delete(route('zones.destroy', $ownZone))->assertForbidden();
        $this->actingAs($boss)->get(route('zones.boundary'))
            ->assertOk()
            ->assertJsonPath('feature.properties.id', $ownZone->id);
    }

    public function test_boss_can_open_zone_management_page_and_receive_primary_boundary_geojson(): void
    {
        $organization = $this->organization('zone-management-page');
        $group = $this->group('zone-management-page');
        $boss = $this->actor('boss', $organization->id, $group->id);
        $primaryZone = $this->zone($boss, $organization->id, 'zone-management-primary');

        $this->actingAs($boss)->get(route('zones.manage'))->assertOk();
        $this->actingAs($boss)->get(route('zones.index'))
            ->assertOk()
            ->assertJsonCount(0, 'features');
        $this->actingAs($boss)->get(route('zones.boundary'))
            ->assertOk()
            ->assertJsonPath('feature.properties.id', $primaryZone->id)
            ->assertJsonPath('feature.properties.readOnly', true);
    }

    public function test_superadmin_can_create_valid_zone_and_geometry_stays_epsg_4326(): void
    {
        $admin = $this->actor('superadmin');

        $this->actingAs($admin)->postJson(route('zones.store'), $this->validZonePayload("SQL'); DROP TABLE users;--"))
            ->assertOk()
            ->assertJsonPath('success', true);

        $zone = Zone::query()->where('name', "SQL'); DROP TABLE users;--")->firstOrFail();
        $geometry = DB::selectOne('SELECT ST_SRID(geom) AS srid, ST_IsValid(geom) AS valid FROM zone WHERE id = ?', [$zone->id]);
        $this->assertSame(4326, (int) $geometry->srid);
        $this->assertTrue((bool) $geometry->valid);
        $this->assertTrue(DB::table('users')->where('id', $admin->id)->exists());
    }

    public function test_invalid_self_intersecting_zone_geometry_is_rejected(): void
    {
        $admin = $this->actor('superadmin');
        $invalid = ['type' => 'Polygon', 'coordinates' => [[
            [49.0, 34.0], [50.0, 35.0], [49.0, 35.0], [50.0, 34.0], [49.0, 34.0],
        ]]];

        $this->actingAs($admin)->postJson(route('zones.store'), ['name' => 'invalid', 'geometry' => $invalid])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('geometry');
    }

    private function actor(string $roleName, ?int $organizationId = null, ?int $groupId = null, ?int $zoneId = null): User
    {
        if ($roleName === 'boss') {
            Role::findOrCreate('supervisor', 'web');
        }
        $role = Role::findOrCreate($roleName, 'web');
        $user = User::query()->create([
            'name' => ucfirst($roleName),
            'username' => $roleName.'-'.uniqid(),
            'email' => $roleName.'-'.uniqid().'@example.test',
            'password' => 'StrongPass123',
            'role_id' => $role->id,
            'organization_id' => $organizationId,
            'group_user_id' => $groupId,
            'zone_id' => $zoneId,
        ]);
        $user->assignRole($role);

        $permissions = match ($roleName) {
            'superadmin' => ['user.view', 'user.create', 'user.edit', 'user.delete', 'zone.view', 'zone.create', 'zone.edit', 'zone.delete'],
            'boss' => ['user.view', 'user.create', 'user.edit', 'zone.view', 'zone.create', 'zone.edit', 'zone.delete'],
            default => [],
        };
        foreach ($permissions as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user;
    }

    private function organizations(): array
    {
        return [
            $this->organization('one'),
            $this->organization('two'),
        ];
    }

    private function organization(string $suffix): Organization
    {
        return Organization::query()->create(['code' => $suffix.'-'.uniqid(), 'name' => 'Organization '.$suffix]);
    }

    private function createGroups(): array
    {
        return [$this->group('one'), $this->group('two')];
    }

    private function group(string $suffix): GroupUser
    {
        return GroupUser::query()->create(['name' => $suffix.'-'.uniqid()]);
    }

    private function zone(User $creator, int $organizationId, string $name): Zone
    {
        $row = DB::selectOne(
            'INSERT INTO zone (name, creator_id, organization_id, geom, created_at, updated_at)
             VALUES (?, ?, ?, ST_MakeEnvelope(49.0, 34.0, 50.0, 35.0, 4326), now(), now()) RETURNING id',
            [$name.'-'.uniqid(), $creator->id, $organizationId]
        );

        $zone = Zone::query()->findOrFail($row->id);
        if ($creator->hasRole('boss') && $creator->group_user_id !== null) {
            $creator->update(['organization_id' => $organizationId, 'zone_id' => $zone->id]);
            $group = GroupUser::query()->findOrFail($creator->group_user_id);
            $group->update(['organization_id' => $organizationId, 'zone_id' => $zone->id]);
            $creator->managedGroups()->syncWithoutDetaching([$group->id => ['assigned_by' => $creator->id]]);
        }

        return $zone;
    }

    private function userPayload(string $username, string $role): array
    {
        return [
            'name' => 'Test '.$username,
            'username' => $username,
            'email' => $username.'@example.test',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'role' => $role,
        ];
    }

    private function validZonePayload(string $name): array
    {
        return [
            'name' => $name,
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [[[49.0, 34.0], [50.0, 34.0], [50.0, 35.0], [49.0, 35.0], [49.0, 34.0]]],
            ],
        ];
    }

}
