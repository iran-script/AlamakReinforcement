<?php

namespace App\Services;

use App\Models\Menu;

class MenuService
{
    public function get()
    {
        return Menu::whereNull('parent_id')
            ->where('visible', true)
            ->with('children')
            ->orderBy('sort')
            ->get();
    }
}