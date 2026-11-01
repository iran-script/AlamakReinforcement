<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

class MenuSeeder extends Seeder
{
    /**
     * Menu route names are the stable identities for links. Groups use a reserved,
     * non-route value so their identity also survives title and ordering changes.
     * Existing menu rows are never deleted or updated by this seeder.
     */
    private const MENU = [
        ['key' => '__group:dashboard', 'title' => 'داشبورد', 'icon' => 'bi bi-speedometer2', 'sort' => 10, 'children' => [
            ['route' => 'dashboard', 'title' => 'داشبورد مدیریتی', 'icon' => 'bi bi-grid-1x2', 'permission' => 'dashboard.view'],
        ]],
        ['key' => '__group:gis', 'title' => 'مدیریت GIS', 'icon' => 'bi bi-map', 'sort' => 20, 'children' => [
            ['route' => 'map', 'title' => 'نقشه علمک‌ها', 'icon' => 'bi bi-map', 'permission' => 'map.view'],
            ['route' => 'riserIndex', 'title' => 'مدیریت علمک‌ها', 'icon' => 'bi bi-geo', 'permission' => 'riser.view'],
            ['route' => 'zones.manage', 'title' => 'مدیریت Zoneها', 'icon' => 'bi bi-bounding-box', 'permission' => 'zone.view'],
        ]],
        ['key' => '__group:repairs', 'title' => 'تعمیرات', 'icon' => 'bi bi-tools', 'sort' => 30, 'children' => [
            ['route' => 'maintenance-cases.index', 'title' => 'پرونده‌های تعمیر', 'icon' => 'bi bi-clipboard2-check', 'permission' => 'maintenance.case.view'],
            ['route' => 'operation-category.index', 'title' => 'انواع عملیات', 'icon' => 'bi bi-list-check', 'permission' => 'operation-category.view'],
        ]],
        ['key' => '__group:materials', 'title' => 'مصالح', 'icon' => 'bi bi-box-seam', 'sort' => 40, 'children' => [
            ['route' => 'material.index', 'title' => 'مدیریت مصالح', 'icon' => 'bi bi-box-seam', 'permission' => 'material.view'],
            ['route' => 'material-category.index', 'title' => 'دسته‌بندی مصالح و کالا', 'icon' => 'bi bi-tags', 'permission' => 'material-category.view'],
        ]],
        ['key' => '__group:reports', 'title' => 'گزارش‌ها', 'icon' => 'bi bi-file-earmark-bar-graph', 'sort' => 50, 'children' => [
            ['route' => 'reports.index', 'title' => 'گزارش‌ساز و گزارش‌های ذخیره‌شده', 'icon' => 'bi bi-file-earmark-spreadsheet', 'permission' => 'report.view'],
            ['route' => 'audit.index', 'title' => 'ردیابی رویدادها', 'icon' => 'bi bi-clock-history'],
        ]],
        ['key' => '__group:system', 'title' => 'مدیریت سیستم', 'icon' => 'bi bi-gear', 'sort' => 60, 'children' => [
            ['route' => 'users.index', 'title' => 'مدیریت کاربران', 'icon' => 'bi bi-people', 'permission' => 'user.view'],
            ['route' => 'roles.index', 'title' => 'نقش‌ها و مجوزها', 'icon' => 'bi bi-shield-lock', 'permission' => 'role.view'],
            ['route' => 'groupusers.index', 'title' => 'گروه‌های کاربران', 'icon' => 'bi bi-people-fill', 'permission' => 'groupuser.view'],
            ['route' => 'contract.index', 'title' => 'مدیریت قراردادها', 'icon' => 'bi bi-file-earmark-text', 'permission' => 'contract.view'],
            ['route' => 'platform.forms.index', 'title' => 'مدیریت فرم‌ها', 'icon' => 'bi bi-ui-checks-grid'],
            ['route' => 'platform.workflows.index', 'title' => 'مدیریت گردش‌کار', 'icon' => 'bi bi-diagram-3'],
            ['route' => 'settings.gis.edit', 'title' => 'تنظیمات نقشه GIS', 'icon' => 'bi bi-map'],
        ]],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::MENU as $groupDefinition) {
                $parent = Menu::query()->where('route', $groupDefinition['key'])->first();

                if (! $parent) {
                    $parent = Menu::query()->create([
                        'title' => $groupDefinition['title'],
                        'icon' => $groupDefinition['icon'],
                        'route' => $groupDefinition['key'],
                        'permission' => null,
                        'sort' => $groupDefinition['sort'],
                        'visible' => 1,
                        'is_active' => true,
                    ]);
                }

                foreach ($groupDefinition['children'] as $item) {
                    if (! Route::has($item['route'])) {
                        continue;
                    }

                    // Route name is the stable key. Preserve any existing/customized row.
                    if (Menu::query()->where('route', $item['route'])->exists()) {
                        continue;
                    }

                    Menu::query()->create([
                        'parent_id' => $parent->id,
                        'title' => $item['title'],
                        'icon' => $item['icon'],
                        'route' => $item['route'],
                        'permission' => $item['permission'] ?? null,
                        'sort' => (Menu::query()->where('parent_id', $parent->id)->max('sort') ?? 0) + 1,
                        'visible' => 1,
                        'is_active' => true,
                    ]);
                }
            }
        });
    }
}
