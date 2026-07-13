<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Riser extends Model
{
    protected $table = 'riser';

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin()) {
            return $query;
        }

        if (!$user->zone_id) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw("
            ST_Intersects(
                riser.geom,
                (
                    SELECT geom
                    FROM zones
                    WHERE id = ?
                )
            )
        ", [$user->zone_id]);
    }
}
