<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    protected $fillable = ['organization_id', 'asset_type_id', 'code', 'name', 'status', 'properties', 'legacy_source', 'legacy_id', 'geom'];

    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AssetType::class, 'asset_type_id');
    }

    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(AssetRelation::class, 'from_asset_id');
    }

    public function incomingRelations(): HasMany
    {
        return $this->hasMany(AssetRelation::class, 'to_asset_id');
    }

    public function workOrderLinks(): HasMany
    {
        return $this->hasMany(WorkOrderAsset::class);
    }

    public function workOrders(): BelongsToMany
    {
        return $this->belongsToMany(WorkOrder::class, 'work_order_assets')->withPivot('relationship_type')->withTimestamps();
    }
}
