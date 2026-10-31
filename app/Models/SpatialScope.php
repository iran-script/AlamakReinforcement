<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SpatialScope extends Model
{
    protected $fillable = ['organization_id', 'name', 'scope_type', 'properties', 'geom'];

    protected function casts(): array { return ['properties' => 'array']; }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_spatial_scopes')->withPivot('access_level')->withTimestamps();
    }
}
