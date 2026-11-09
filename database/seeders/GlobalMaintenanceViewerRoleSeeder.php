<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class GlobalMaintenanceViewerRoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'dashboard.view', 'map.view', 'riser.view', 'operation.view', 'maintenance.case.view',
            'maintenance.case.view-all-zones', 'maintenance.history.view', 'report.view', 'report.export',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        Role::findOrCreate('Global Maintenance Viewer', 'web')->syncPermissions(
            Permission::query()->whereIn('name', $permissions)->where('guard_name', 'web')->get()
        );
    }
}
