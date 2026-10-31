<?php

namespace App\Models;

use App\Enums\WorkOrderPriority;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrderType extends Model
{
    protected $fillable = [
        'organization_id', 'key', 'name', 'description', 'default_priority',
        'default_sla_hours', 'configuration', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_priority' => WorkOrderPriority::class,
            'configuration' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
