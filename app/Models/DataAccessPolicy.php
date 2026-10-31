<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataAccessPolicy extends Model
{
    protected $fillable = [
        'organization_id', 'name', 'principal_type', 'principal_id', 'resource_key',
        'actions', 'field_rules', 'conditions', 'spatial_scope_id', 'effect', 'priority', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'actions' => 'array',
            'field_rules' => 'array',
            'conditions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function spatialScope(): BelongsTo { return $this->belongsTo(SpatialScope::class); }
}
