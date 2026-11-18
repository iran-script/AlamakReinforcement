<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class WmsActivationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $permission = Permission::findOrCreate('wms.manage', 'web');
            Permission::findOrCreate('wms.endpoint-approve', 'web');
            $superAdmin = Role::query()->where('name', 'superadmin')->where('guard_name', 'web')->firstOrFail();
            $superAdmin->givePermissionTo($permission);

            $parent = Menu::query()->where('route', '__group:system')->firstOrFail();
            Menu::query()->firstOrCreate(
                ['route' => 'settings.gis.wms'],
                [
                    'parent_id' => $parent->id,
                    'title' => 'مدیریت لایه‌های WMS',
                    'icon' => 'bi bi-layers',
                    'permission' => 'wms.manage',
                    'sort' => (Menu::query()->where('parent_id', $parent->id)->max('sort') ?? 0) + 1,
                    'visible' => true,
                    'is_active' => true,
                ]
            );
        });
    }
}
