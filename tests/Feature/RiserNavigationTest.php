<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Controllers\TileController;
use App\Models\Riser;
use App\Models\Menu;
use App\Models\User;
use App\Services\MenuService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RiserNavigationTest extends TestCase
{
    public function test_empty_database_menu_does_not_render_a_manual_riser_fallback(): void
    {
        $this->actingAs($this->userWithRoleCheck(true));
        $this->stubEmptyMenus();

        $html = view('components.sidebar', ['menus' => collect()])->render();

        $this->assertStringNotContainsString('href="'.route('riserIndex').'"', $html);
    }

    public function test_user_without_riser_permission_does_not_see_riser_link(): void
    {
        $user = $this->userWithRoleCheck(false);
        $user->shouldReceive('isSuperAdmin')->andReturn(false);
        $user->shouldReceive('can')->with('riser.view')->andReturn(false);
        $this->actingAs($user);
        $this->stubEmptyMenus();

        $html = view('components.sidebar', ['menus' => collect()])->render();

        $this->assertStringNotContainsString('href="'.route('riserIndex').'"', $html);
    }

    public function test_empty_database_menu_does_not_render_map_fallback_and_backend_still_requires_permission(): void
    {
        $this->actingAs($this->userWithRoleCheck(true));
        $this->stubEmptyMenus();

        $html = view('components.sidebar', ['menus' => collect()])->render();
        $this->assertStringNotContainsString('href="'.route('map').'"', $html);
        $this->assertContains('check_permission:map.view', app('router')->getRoutes()->getByName('map')->gatherMiddleware());
    }

    public function test_user_without_map_permission_does_not_see_map_link(): void
    {
        $user = $this->userWithRoleCheck(false);
        $user->shouldReceive('can')->with('map.view')->andReturn(false);
        $this->actingAs($user);
        $this->stubEmptyMenus();

        $html = view('components.sidebar', ['menus' => collect()])->render();
        $this->assertStringNotContainsString('href="'.route('map').'"', $html);
    }

    public function test_user_with_map_permission_does_not_get_a_manual_map_fallback(): void
    {
        $user = $this->userWithRoleCheck(false);
        $user->shouldReceive('can')->with('map.view')->andReturn(true);
        $this->actingAs($user);
        $this->stubEmptyMenus();

        $html = view('components.sidebar', ['menus' => collect()])->render();
        $this->assertStringNotContainsString('href="'.route('map').'"', $html);
    }

    public function test_existing_database_map_menu_is_not_duplicated(): void
    {
        $this->actingAs($this->userWithRoleCheck(true));
        $child = new Menu;
        $child->setRawAttributes(['id' => 3, 'title' => 'Map', 'icon' => 'bi bi-map', 'route' => 'map', 'visible' => true]);
        $child->setRelation('children', collect());
        $parent = new Menu;
        $parent->setRawAttributes(['id' => 1, 'title' => 'GIS', 'icon' => 'bi bi-map', 'visible' => true]);
        $parent->setRelation('children', collect([$child]));
        $menus = collect([$parent]);
        $this->stubEmptyMenus($menus);

        $html = view('components.sidebar', ['menus' => $menus])->render();

        $this->assertSame(1, substr_count($html, 'href="'.route('map').'"'));
        $this->assertStringContainsString('Map', $html);
    }

    public function test_tile_endpoint_denies_users_without_map_permission(): void
    {
        $user = $this->userWithRoleCheck(false);
        $user->shouldReceive('hasPermission')->with('map.view')->andReturn(false);
        $request = Request::create('/tiles/riser/1/0/0.pbf');
        $request->setUserResolver(fn () => $user);

        try {
            app(TileController::class)->tile($request, 'riser', 1, 0, 0);
            $this->fail('Expected map permission to be required for MVT tiles.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_map_route_middleware_denies_user_without_map_permission(): void
    {
        $user = $this->userWithRoleCheck(false);
        $user->shouldReceive('hasPermission')->with('map.view')->andReturn(false);
        $this->actingAs($user);

        try {
            app(CheckPermission::class)->handle(Request::create('/map'), fn () => response('allowed'), 'map.view');
            $this->fail('Expected map permission to be required.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_mvt_uses_web_mercator_tiles_and_zone_scoped_query(): void
    {
        $user = $this->userWithRoleCheck(false);
        $user->shouldReceive('hasPermission')->with('map.view')->andReturn(true);
        $user->zone_id = 42;

        DB::shouldReceive('selectOne')->once()->withArgs(function (string $sql, array $bindings): bool {
            return str_contains($sql, 'ST_TileEnvelope')
                && str_contains($sql, 'ST_AsMVTGeom')
                && str_contains($sql, 'ST_AsMVT(')
                && str_contains($sql, 'ST_Intersects')
                && $bindings === [1, 0, 0, 1, 1, 42, 'riser'];
        })->andReturn((object) ['tile' => 'encoded-mvt']);

        $request = Request::create('/tiles/riser/1/0/0.pbf');
        $request->setUserResolver(fn () => $user);
        $response = app(TileController::class)->tile($request, 'riser', 1, 0, 0);

        $this->assertSame('encoded-mvt', $response->getContent());
        $this->assertSame('application/x-protobuf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_existing_database_riser_menu_is_not_duplicated(): void
    {
        $this->actingAs($this->userWithRoleCheck(true));
        $child = new Menu;
        $child->setRawAttributes(['id' => 2, 'title' => 'Risers', 'icon' => 'bi bi-geo', 'route' => 'riserIndex', 'visible' => true]);
        $child->setRelation('children', collect());
        $parent = new Menu;
        $parent->setRawAttributes(['id' => 1, 'title' => 'GIS', 'icon' => 'bi bi-map', 'visible' => true]);
        $parent->setRelation('children', collect([$child]));
        $menus = collect([$parent]);
        $this->stubEmptyMenus($menus);

        $html = view('components.sidebar', ['menus' => $menus])->render();

        $this->assertSame(1, substr_count($html, 'href="'.route('riserIndex').'"'));
    }

    public function test_riser_routes_keep_permission_middleware_and_superadmin_bypass(): void
    {
        $route = app('router')->getRoutes()->getByName('riserIndex');
        $this->assertContains('check_permission:riser.view', $route->gatherMiddleware());

        $this->actingAs($this->userWithRoleCheck(true));
        $response = app(CheckPermission::class)->handle(Request::create('/riser/index'), fn () => response('allowed'), 'riser.view');

        $this->assertSame('allowed', $response->getContent());
    }

    public function test_user_without_riser_permission_is_denied_by_backend_middleware(): void
    {
        $user = $this->userWithRoleCheck(false);
        $user->shouldReceive('hasPermission')->with('riser.view')->andReturn(false);
        $this->actingAs($user);

        try {
            app(CheckPermission::class)->handle(Request::create('/riser/index'), fn () => response('allowed'), 'riser.view');
            $this->fail('Expected an authorization exception.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_riser_visibility_scope_keeps_zone_restriction_and_allows_superadmin(): void
    {
        $zoneUser = $this->userWithRoleCheck(false);
        $zoneUser->shouldReceive('isSuperAdmin')->andReturn(false);
        $zoneUser->zone_id = 42;

        $zoneQuery = Riser::query()->visibleTo($zoneUser);
        $this->assertStringContainsString('ST_Intersects', $zoneQuery->toSql());
        $this->assertSame([42], $zoneQuery->getBindings());

        $superadmin = $this->userWithRoleCheck(true);
        $superadmin->shouldReceive('isSuperAdmin')->andReturn(true);
        $this->assertSame([], Riser::query()->visibleTo($superadmin)->getBindings());
    }

    public function test_empty_riser_list_renders_without_database_rows(): void
    {
        $this->actingAs($this->userWithRoleCheck(true));
        $this->stubEmptyMenus();
        $risers = new LengthAwarePaginator([], 0, 20, 1, ['path' => '/riser/index']);

        $html = view('riser.index', [
            'risers' => $risers,
            'statuses' => collect(),
            'totalVisible' => 0,
            'withCode' => 0,
            'perPage' => 25,
            'filters' => [],
        ])->render();

        $this->assertStringContainsString('<td colspan="6"', $html);
    }

    private function userWithRoleCheck(bool $isSuperadmin): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->setRawAttributes(['id' => 1, 'name' => 'Navigation test']);
        $user->shouldReceive('hasRole')->with('superadmin')->andReturn($isSuperadmin);
        $user->shouldReceive('hasPermission')->with('maintenance.case.view')->andReturn(false);
        $user->shouldReceive('isSuperAdmin')->andReturn($isSuperadmin);
        $user->shouldReceive('checkPermissionTo')->andReturn(false);

        return $user;
    }

    private function stubEmptyMenus($items = null): void
    {
        Gate::before(fn () => false);
        $menus = Mockery::mock(MenuService::class);
        $menus->shouldReceive('get')->andReturn($items ?? collect());
        $this->app->instance(MenuService::class, $menus);
    }
}
