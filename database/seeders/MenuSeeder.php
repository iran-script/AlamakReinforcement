<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Menu;

class MenuSeeder extends Seeder
{
    public function run()
    {
        Menu::truncate();

        // داشبورد
        Menu::create([
            'title'=>'داشبورد',
            'icon'=>'bi bi-speedometer2',
            'route'=>'dashboard',
            'sort'=>1
        ]);

        // GIS
        $gis=Menu::create([
            'title'=>'GIS',
            'icon'=>'bi bi-map',
            'sort'=>2
        ]);

        Menu::create([
            'parent_id'=>$gis->id,
            'title'=>'نقشه',
            'icon'=>'bi bi-map',
            'route'=>'map',
            'sort'=>1
        ]);

        Menu::create([
            'parent_id'=>$gis->id,
            'title'=>'علمک ها',
            'icon'=>'bi bi-geo',
            'route'=>'riserIndex',
            'sort'=>2
        ]);

        Menu::create([
            'parent_id'=>$gis->id,
            'title'=>'زون ها',
            'icon'=>'bi bi-bounding-box',
            'route'=>'zones.manage',
            'sort'=>3
        ]);

        // تعمیرات
        $maintenance=Menu::create([
            'title'=>'تعمیرات',
            'icon'=>'bi bi-tools',
            'sort'=>3
        ]);

        Menu::create([
            'parent_id'=>$maintenance->id,
            'title'=>'عملیات',
            'icon'=>'bi bi-wrench',
            'route'=>'operations.index',
            'sort'=>1
        ]);

        Menu::create([
            'parent_id'=>$maintenance->id,
            'title'=>'پیمانکاران',
            'icon'=>'bi bi-person-workspace',
            'route'=>'contractors.index',
            'sort'=>2
        ]);

        // اطلاعات پایه
        $base=Menu::create([
            'title'=>'اطلاعات پایه',
            'icon'=>'bi bi-database',
            'sort'=>4
        ]);

        Menu::create([
            'parent_id'=>$base->id,
            'title'=>'دسته بندی عملیات',
            'icon'=>'bi bi-list-check',
            'route'=>'operation-category.index',
            'sort'=>1
        ]);

        Menu::create([
            'parent_id'=>$base->id,
            'title'=>'دسته بندی مصالح',
            'icon'=>'bi bi-box',
            'route'=>'material-category.index',
            'sort'=>2
        ]);

        Menu::create([
            'parent_id'=>$base->id,
            'title'=>'مصالح',
            'icon'=>'bi bi-box-seam',
            'route'=>'material.index',
            'sort'=>3
        ]);

        // گزارشات
        $report=Menu::create([
            'title'=>'گزارشات',
            'icon'=>'bi bi-file-earmark-bar-graph',
            'sort'=>5
        ]);

        Menu::create([
            'parent_id'=>$report->id,
            'title'=>'گزارشات',
            'icon'=>'bi bi-file-earmark-text',
            'route'=>'reports.index',
            'sort'=>1
        ]);

        // کاربران
        $users=Menu::create([
            'title'=>'کاربران',
            'icon'=>'bi bi-people',
            'sort'=>6
        ]);

        Menu::create([
            'parent_id'=>$users->id,
            'title'=>'مدیریت کاربران',
            'icon'=>'bi bi-person',
            'route'=>'users.index',
            'sort'=>1
        ]);

    }
}