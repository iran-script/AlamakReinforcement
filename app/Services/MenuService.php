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
        'boss.maintenance-cases.index' => 'operation.approve',
    ];

    public function get(): Collection
    {
        $user = auth()->user();
        if (! $user) {
            return new Collection();
        }

        $this->seenRoutes = [];

        if ($user->isGlobalMaintenanceViewer()) {
            return collect([
                ['route' => 'dashboard', 'title' => 'داشبورد', 'icon' => 'bi bi-speedometer2', 'permission' => 'dashboard.view'],
                ['route' => 'map', 'title' => 'نقشه', 'icon' => 'bi bi-map', 'permission' => 'map.view'],
                ['route' => 'riserIndex', 'title' => 'علمک‌ها', 'icon' => 'bi bi-geo', 'permission' => 'riser.view'],
                ['route' => 'maintenance-cases.index', 'title' => 'پرونده‌های تعمیرات', 'icon' => 'bi bi-clipboard2-check', 'permission' => 'maintenance.case.view'],
                ['route' => 'audit.index', 'title' => 'تاریخچه تغییرات', 'icon' => 'bi bi-clock-history', 'permission' => 'maintenance.history.view'],
                ['route' => 'reports.index', 'title' => 'گزارش‌ها', 'icon' => 'bi bi-file-earmark-bar-graph', 'permission' => 'report.view'],
            ])->map(function (array $item): Menu {
                return new Menu($item + ['visible' => true, 'is_active' => true]);
            })->filter(fn (Menu $menu): bool => Route::has($menu->route) && $this->mayVisit($menu, $user))->values();
        }

        $menus = Menu::query()
            ->whereNull('parent_id')
            ->where('visible', 1)
            ->where('is_active', true)
            ->with('children')
            ->orderBy('sort')
            ->get();

        if ($user->hasRole('boss') && $user->can('operation.approve') && Route::has('boss.maintenance-cases.index')) {
            $repairs = $menus->firstWhere('route', '__group:repairs');
            if ($repairs && ! $repairs->children->contains(fn (Menu $child): bool => $child->route === 'boss.maintenance-cases.index')) {
                $approvalMenu = new Menu([
                    'title' => 'تأیید تعمیرات',
                    'icon' => 'bi bi-check2-square',
                    'route' => 'boss.maintenance-cases.index',
                    'permission' => 'operation.approve',
                    'sort' => 35,
                    'visible' => true,
                    'is_active' => true,
                ]);
                $repairs->setRelation('children', $repairs->children->push($approvalMenu));
            }
        }

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
