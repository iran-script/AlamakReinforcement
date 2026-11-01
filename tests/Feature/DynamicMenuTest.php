<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\GroupUser;
use App\Models\Organization;
use App\Models\User;
use App\Services\MenuService;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DynamicMenuTest extends TestCase
{
    use DatabaseTransactions;

    public function test_menu_seeder_is_idempotent_and_preserves_custom_rows(): void
    {
        $custom = Menu::query()->create([
            'title' => 'منوی سفارشی',
            'route' => 'custom.route',
            'sort' => 99,
            'visible' => 1,
            'is_active' => true,
        ]);

        $seeder = app(MenuSeeder::class);
        $seeder->run();
        $firstCount = Menu::query()->count();
        $seeder->run();

        $this->assertSame($firstCount, Menu::query()->count());
        $this->assertDatabaseHas('menus', ['id' => $custom->id, 'title' => 'منوی سفارشی', 'route' => 'custom.route']);
        $this->assertSame(1, Menu::query()->where('route', 'material.index')->count());
        $this->assertSame(1, Menu::query()->where('route', '__group:gis')->count());
    }

    public function test_superadmin_sees_available_core_navigation(): void
    {
        $this->seedRolesAndMenu();
        $admin = $this->makeUser();
        $admin->assignRole(Role::findByName('superadmin', 'web'));
        $this->actingAs($admin);

        $routes = app(MenuService::class)->get()->flatMap(fn (Menu $menu) => $this->routesUnder($menu));

        foreach (['dashboard', 'material.index', 'material-category.index', 'reports.index', 'map', 'riserIndex', 'users.index', 'zones.manage'] as $route) {
            $this->assertContains($route, $routes->all(), "Expected menu route {$route} for superadmin.");
        }

        $this->assertNotContains('operations.index', $routes->all(), 'The operations route returns JSON and has no management page view.');
    }

    public function test_user_sees_only_menus_for_real_permissions(): void
    {
        $this->seedRolesAndMenu();
        $user = $this->makeUser();
        $permission = Permission::findOrCreate('material.view', 'web');
        $user->givePermissionTo($permission);
        $this->actingAs($user);

        $routes = app(MenuService::class)->get()->flatMap(fn (Menu $menu) => $this->routesUnder($menu));

        $this->assertContains('material.index', $routes->all());
        $this->assertNotContains('users.index', $routes->all());
        $this->assertNotContains('map', $routes->all());
    }

    public function test_boss_navigation_is_limited_to_permissions_already_granted_to_boss(): void
    {
        $this->seedRolesAndMenu();
        $bossRole = Role::findOrCreate('boss', 'web');
        $bossRole->syncPermissions([
            Permission::findOrCreate('user.view', 'web'),
            Permission::findOrCreate('zone.view', 'web'),
        ]);
        $organization = Organization::query()->create(['code' => 'menu-boss-'.Str::random(8), 'name' => 'Menu Boss']);
        $zoneId = \Illuminate\Support\Facades\DB::selectOne(
            "INSERT INTO zone (name, organization_id, geom, created_at, updated_at) VALUES (?, ?, ST_MakeEnvelope(49,34,50,35,4326), now(), now()) RETURNING id",
            ['menu-boss-zone-'.Str::random(8), $organization->id]
        )->id;
        $group = GroupUser::query()->create(['name' => 'Boss Group', 'organization_id' => $organization->id, 'zone_id' => $zoneId]);
        $boss = $this->makeUser(['group_user_id' => $group->id, 'organization_id' => $organization->id, 'zone_id' => $zoneId]);
        $boss->assignRole($bossRole);
        $boss->managedGroups()->syncWithoutDetaching([$group->id => ['assigned_by' => $boss->id]]);
        $this->actingAs($boss);

        $routes = app(MenuService::class)->get()->flatMap(fn (Menu $menu) => $this->routesUnder($menu));

        $this->assertContains('users.index', $routes->all());
        $this->assertContains('zones.manage', $routes->all());
        $this->assertNotContains('roles.index', $routes->all());
        $this->assertNotContains('contract.index', $routes->all());
    }

    public function test_menu_service_and_sidebar_discard_routes_that_do_not_exist(): void
    {
        $this->seedRolesAndMenu();
        $root = Menu::query()->where('route', '__group:gis')->firstOrFail();
        $root->children()->create([
            'title' => 'لینک نامعتبر',
            'route' => 'missing.route',
            'sort' => 99,
            'visible' => 1,
            'is_active' => true,
        ]);
        $admin = $this->makeUser();
        $admin->assignRole(Role::findByName('superadmin', 'web'));
        $this->actingAs($admin);

        $routes = app(MenuService::class)->get()->flatMap(fn (Menu $menu) => $this->routesUnder($menu));
        $html = view('components.sidebar', ['menus' => app(MenuService::class)->get()])->render();

        $this->assertNotContains('missing.route', $routes->all());
        $this->assertStringNotContainsString('لینک نامعتبر', $html);
        $this->assertSame(1, substr_count($html, 'href="'.route('map').'"'));
    }

    public function test_management_resources_have_backend_permission_checks(): void
    {
        foreach ([
            'material.index' => 'check_permission:material.view',
            'material-category.index' => 'check_permission:material-category.view',
            'operation-category.index' => 'check_permission:operation-category.view',
            'contract.index' => 'check_permission:contract.view',
        ] as $routeName => $middleware) {
            $this->assertContains($middleware, Route::getRoutes()->getByName($routeName)->gatherMiddleware());
        }
    }

    public function test_user_without_material_permission_cannot_open_management_route_directly(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('material.index'))
            ->assertForbidden();
    }

    private function seedRolesAndMenu(): void
    {
        foreach ([
            'dashboard.view', 'map.view', 'zone.view', 'riser.view', 'operation.view',
            'operation-category.view', 'material-category.view', 'material.view', 'report.view',
            'user.view', 'role.view', 'groupuser.view', 'contract.view',
        ] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $superadmin = Role::findOrCreate('superadmin', 'web');
        $superadmin->syncPermissions(Permission::query()->where('guard_name', 'web')->get());
        app(MenuSeeder::class)->run();
    }

    private function routesUnder(Menu $menu)
    {
        return collect([$menu->route])->filter(fn ($route) => $route && Route::has($route))
            ->concat($menu->children->flatMap(fn (Menu $child) => $this->routesUnder($child)))
            ->values();
    }

    private function makeUser(array $attributes = []): User
    {
        $legacyRoleId = Role::findOrCreate('viewer', 'web')->id;

        return User::query()->create(array_merge([
            'name' => 'Navigation test user',
            'username' => 'menu-test-'.Str::lower(Str::random(10)),
            'password' => Hash::make(Str::random(40)),
            'role_id' => $legacyRoleId,
        ], $attributes));
    }
}
