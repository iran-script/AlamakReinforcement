<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GisMapLayer extends Model
{
    protected $fillable = [
        'organization_id',
        'key',
        'title',
        'type',
        'endpoint',
        'wms_layers',
        'format',
        'version',
        'styles',
        'server_type',
        'transparent',
        'min_zoom',
        'max_zoom',
        'is_active',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'transparent' => 'boolean',
            'is_active' => 'boolean',
            'min_zoom' => 'integer',
            'max_zoom' => 'integer',
        ];
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
