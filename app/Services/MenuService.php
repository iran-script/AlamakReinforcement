<?php

namespace App\Services;

use App\Models\Menu;

class MenuService
{
    public function get()
    {
        return Menu::whereNull('parent_id')
            ->where('visible',1)
            ->with([
                'children'=>function($q){

                    $q->where('visible',1)
                      ->orderBy('sort');

                }
            ])
            ->orderBy('sort')
            ->get();
    }
}