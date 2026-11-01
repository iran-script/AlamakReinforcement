<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MaintenanceWorkflowAccessSeeder extends Seeder
{
    private const CLUSTER_ID = '6882458043277858192';

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'boss' => [
            'user.view', 'user.create', 'user.edit', 'zone.view', 'dashboard.view', 'map.view',
            'riser.view', 'operation.view', 'operation.edit', 'operation.approve',
            'maintenance.case.view', 'report.view',
        ],
        'supervisor' => [
            'dashboard.view', 'map.view', 'riser.view', 'operation.view', 'operation.create',
            'operation.approve', 'report.view', 'maintenance.case.create', 'maintenance.case.view',
        ],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('MaintenanceWorkflowAccessSeeder is restricted to APP_ENV=local.');
        }

        $identity = DB::selectOne(
            'SELECT current_database() AS database, inet_server_port() AS port, system_identifier FROM pg_control_system()'
        );
        if ($identity->database !== 'alamakreinforcement'
            || (int) $identity->port !== 5432
            || (string) $identity->system_identifier !== self::CLUSTER_ID) {
            throw new RuntimeException('Connected database is not the approved local development cluster.');
        }

        DB::transaction(function (): void {
            foreach (self::ROLE_PERMISSIONS as $roleName => $permissionNames) {
                if ($roleName === 'boss') {
                    $role = Role::query()->firstOrCreate(['name' => 'boss', 'guard_name' => 'web']);
                } else {
                    $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();
                    if (! $role) {
                        throw new RuntimeException("Required existing role [{$roleName}] is missing; no role was assigned.");
                    }
                }

                foreach ($permissionNames as $permissionName) {
                    $permission = Permission::query()->firstOrCreate([
                        'name' => $permissionName,
                        'guard_name' => 'web',
                    ]);
                    if (! $role->hasPermissionTo($permission)) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
