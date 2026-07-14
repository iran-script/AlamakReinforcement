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
        'module',
        'sort',
        'visible'
    ];

    protected $casts = [
        'visible' => 'boolean'
    ];

    /**
     * منوی والد
     */
    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    /**
     * زیرمنوها
     */
    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')
            ->where('visible', true)
            ->orderBy('sort');
    }
}