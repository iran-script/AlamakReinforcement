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
        /*
        |--------------------------------------------------------------------------
        | Permissions
        |--------------------------------------------------------------------------
        */

        $permissions = [

            'dashboard.view',

            'map.view',

            'zone.view',
            'zone.create',
            'zone.edit',
            'zone.delete',

            'riser.view',
            'riser.create',
            'riser.edit',
            'riser.delete',

            'operation.view',
            'operation.create',
            'operation.edit',
            'operation.delete',
            'operation.approve',

            'operation-category.view',
            'operation-category.create',
            'operation-category.edit',
            'operation-category.delete',

            'material-category.view',
            'material-category.create',
            'material-category.edit',
            'material-category.delete',

            'material.view',
            'material.create',
            'material.edit',
            'material.delete',

            'contractor.view',
            'contractor.create',
            'contractor.edit',
            'contractor.delete',

            'report.view',

            'user.view',
            'user.create',
            'user.edit',
            'user.delete',

            'role.view',
            'role.create',
            'role.edit',
            'role.delete',

        ];

        foreach ($permissions as $permission) {

            Permission::findOrCreate($permission, 'web');

        }

        /*
        |--------------------------------------------------------------------------
        | Roles
        |--------------------------------------------------------------------------
        */

        $superAdmin = Role::findOrCreate('superadmin', 'web');

        Role::findOrCreate('admin', 'web');

        Role::findOrCreate('operator', 'web');

        Role::findOrCreate('viewer', 'web');

        /*
        |--------------------------------------------------------------------------
        | SuperAdmin
        |--------------------------------------------------------------------------
        */

        $superAdmin->syncPermissions(
            Permission::all()
        );

        /*
        |--------------------------------------------------------------------------
        | اولین کاربر = SuperAdmin
        |--------------------------------------------------------------------------
        */

        $user = User::first();

        if ($user) {

            $user->assignRole($superAdmin);

        }
    }
}