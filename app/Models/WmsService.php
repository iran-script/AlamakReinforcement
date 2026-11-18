<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WmsService extends Model
{
    protected $fillable = ['organization_id', 'name', 'url', 'version', 'is_active', 'default_transparent', 'capabilities_metadata', 'last_checked_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'default_transparent' => 'boolean', 'capabilities_metadata' => 'array', 'last_checked_at' => 'datetime'];
    }

    public function layers(): HasMany
    {
        return $this->hasMany(WmsLayer::class)->orderBy('sort_order')->orderBy('id');
    }
}
