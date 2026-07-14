<?php

namespace App\View\Components;

use Illuminate\View\Component;
use App\Services\MenuService;

class Sidebar extends Component
{
    public $menus;

    public function __construct(MenuService $service)
    {
        $this->menus = $service->get();
    }

    public function render()
    {
        return view('components.sidebar');
    }
}