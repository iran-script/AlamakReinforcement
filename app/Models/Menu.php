<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Menu extends Model
{
    protected $fillable = [
        'parent_id',
        'title',
        'icon',
        'route',
        'permission',
        'sort',
        'visible'
    ];

    protected $casts = [
        'visible' => 'boolean'
    ];

    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')
            ->where('visible', true)
            ->with('children')
            ->orderBy('sort');
    }

    public function isActive()
    {
        return $this->route && request()->routeIs($this->route);
    }
}