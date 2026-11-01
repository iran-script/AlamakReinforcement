<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GisMapSetting extends Model
{
    protected $fillable = [
        'organization_id', 'name', 'service_type', 'base_tile_url', 'style_url',
        'base_tile_attribution', 'min_zoom', 'max_zoom', 'is_active', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'min_zoom' => 'integer', 'max_zoom' => 'integer'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
