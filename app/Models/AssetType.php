<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetType extends Model
{
    protected $fillable = ['organization_id', 'key', 'name', 'geometry_kind', 'attribute_schema', 'ui_schema', 'is_active'];

    protected function casts(): array
    {
        return ['attribute_schema' => 'array', 'ui_schema' => 'array', 'is_active' => 'boolean'];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function assets(): HasMany { return $this->hasMany(Asset::class); }
}
