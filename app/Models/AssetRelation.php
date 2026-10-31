<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRelation extends Model
{
    protected $fillable = ['organization_id', 'from_asset_id', 'to_asset_id', 'relation_type', 'properties'];

    protected function casts(): array { return ['properties' => 'array']; }
    public function fromAsset(): BelongsTo { return $this->belongsTo(Asset::class, 'from_asset_id'); }
    public function toAsset(): BelongsTo { return $this->belongsTo(Asset::class, 'to_asset_id'); }
}
