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
        'visible',
        'is_active',
    ];

    protected $casts = [
        'visible' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Menu::class, 'parent_id')
            ->where('visible', 1)
            ->where('is_active', true)
            ->with('children')
            ->orderBy('sort');
    }

    public function isActive()
    {
        if (! $this->route || str_starts_with($this->route, '__group:')) {
            return false;
        }

        $routeName = request()->route()?->getName();
        if (! $routeName) {
            return false;
        }

        if (request()->routeIs($this->route)) {
            return true;
        }

        return str_ends_with($this->route, '.index')
            && request()->routeIs(substr($this->route, 0, -strlen('.index')).'.*');
    }

    public function hasActiveDescendant(): bool
    {
        return $this->children->contains(fn (Menu $child) => $child->isActive() || $child->hasActiveDescendant());
    }
}
