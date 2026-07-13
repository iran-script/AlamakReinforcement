<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view-map',
            'view-riser',
            'create-riser',
            'edit-riser',
            'delete-riser',
            'view-valve',
            'create-valve',
            'edit-valve',
            'delete-valve',
            'manage-users',
            'manage-zones',
        ];

        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        $superAdmin = Role::findOrCreate('superadmin', 'web');
        $admin = Role::findOrCreate('admin', 'web');
        $operator = Role::findOrCreate('operator', 'web');
        $viewer = Role::findOrCreate('viewer', 'web');

        $superAdmin->syncPermissions(Permission::all());

        $admin->syncPermissions([
            'view-map',
            'view-riser',
            'create-riser',
            'edit-riser',
            'delete-riser',
            'view-valve',
            'create-valve',
            'edit-valve',
            'delete-valve',
            'manage-zones',
        ]);

        $operator->syncPermissions([
            'view-map',
            'view-riser',
            'create-riser',
            'edit-riser',
            'view-valve',
            'create-valve',
            'edit-valve',
        ]);

        $viewer->syncPermissions([
            'view-map',
            'view-riser',
            'view-valve',
        ]);

        $user = User::where('username', 'ایمیل_مدیر_کل_خودت')->first();

        if ($user) {
            $user->syncRoles(['superadmin']);
        }
    }
}
