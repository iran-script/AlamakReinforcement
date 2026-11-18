<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WmsLayer extends Model
{
    protected $fillable = ['wms_service_id', 'layer_name', 'title', 'abstract', 'supported_crs', 'supported_formats', 'version', 'queryable', 'legend_url', 'metadata', 'display_name', 'is_enabled', 'default_visible', 'opacity', 'sort_order'];

    protected function casts(): array
    {
        return ['supported_crs' => 'array', 'supported_formats' => 'array', 'metadata' => 'array', 'queryable' => 'boolean', 'is_enabled' => 'boolean', 'default_visible' => 'boolean', 'opacity' => 'float', 'sort_order' => 'integer'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(WmsService::class, 'wms_service_id');
    }
}
