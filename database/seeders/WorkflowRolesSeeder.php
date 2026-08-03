<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class WorkflowRolesSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'field-supervisor']); // ناظر
        Role::firstOrCreate(['name' => 'supervisor']);        // ناظر بازرسی فنی
        Role::firstOrCreate(['name' => 'district-chief']);    // رئیس ناحیه — فقط مشاهده

        $flag      = Permission::firstOrCreate(['name' => 'flag-riser-operation']);
        $create    = Permission::firstOrCreate(['name' => 'create-operation']);
        $leakCheck = Permission::firstOrCreate(['name' => 'leak-check-operation']);

        Role::findByName('field-supervisor')->givePermissionTo([$flag, $create]);
        Role::findByName('supervisor')->givePermissionTo([$leakCheck]);
        // district-chief عمداً بدون permission → فقط مشاهده
    }
}