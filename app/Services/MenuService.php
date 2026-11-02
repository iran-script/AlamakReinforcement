<?php

namespace App\Services;

use App\Models\FormDefinition;
use App\Models\GisMapSetting;
use App\Models\Menu;
use App\Models\Operation;
use App\Models\Organization;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\Zone;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Collection;

class MenuService
{
    private array $seenRoutes = [];

    /** Routes that are API responses rather than interactive page destinations. */
    private const NON_PAGE_ROUTES = ['operations.index'];

    private const POLICY_ROUTES = [
        'operations.index' => [Operation::class, 'viewAny'],
        'zones.manage' => [Zone::class, 'viewAny'],
        'users.index' => [User::class, 'viewAny'],
        'organizations.index' => [Organization::class, 'viewAny'],
        'platform.forms.index' => [FormDefinition::class, 'viewAny'],
        'platform.workflows.index' => [WorkflowDefinition::class, 'viewAny'],
        'settings.gis.edit' => [GisMapSetting::class, 'create'],
    ];

    private const ROUTE_PERMISSIONS = [
        'dashboard' => 'dashboard.view',
        'map' => 'map.view',
        'riserIndex' => 'riser.view',
        'operation-category.index' => 'operation-category.view',
        'material-category.index' => 'material-category.view',
        'material.index' => 'material.view',
        'reports.index' => 'report.view',
        'users.index' => 'user.view',
        'organizations.index' => null,
        'roles.index' => 'role.view',
        'groupusers.index' => 'groupuser.view',
        'contract.index' => 'contract.view',
    ];

    public function get(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return new Collection();
        }

        $this->seenRoutes = [];

        $menus = Menu::query()
            ->whereNull('parent_id')
            ->where('visible', 1)
            ->where('is_active', true)
            ->with('children')
            ->orderBy('sort')
            ->get();

        return $menus->map(fn (Menu $menu) => $this->filterMenu($menu, $user))
            ->filter()
            ->values();
    }

    private function filterMenu(Menu $menu, User $user): ?Menu
    {
        $menu->setRelation('children', $menu->children
            ->map(fn (Menu $child) => $this->filterMenu($child, $user))
            ->filter()
            ->values());

        $isGroup = $menu->children->isNotEmpty();
        $hasRoute = $menu->route
            && ! str_starts_with($menu->route, '__group:')
            && ! in_array($menu->route, self::NON_PAGE_ROUTES, true)
            && Route::has($menu->route);

        if (! $isGroup && (! $hasRoute || ! $this->mayVisit($menu, $user))) {
            return null;
        }

        if (! $isGroup) {
            if (isset($this->seenRoutes[$menu->route])) {
                return null;
            }

            $this->seenRoutes[$menu->route] = true;
        }

        return $menu;
    }

    private function mayVisit(Menu $menu, User $user): bool
    {
        if ($menu->route === 'audit.index' && ! $user->isSuperAdmin()) {
            return false;
        }

        $policy = self::POLICY_ROUTES[$menu->route] ?? null;
        if ($policy && ! Gate::forUser($user)->allows($policy[1], $policy[0])) {
            return false;
        }

        $requiredPermissions = array_filter([
            self::ROUTE_PERMISSIONS[$menu->route] ?? null,
            $menu->permission,
        ]);

        foreach ($requiredPermissions as $permission) {
            if (! $user->hasRole('superadmin') && ! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }
}
