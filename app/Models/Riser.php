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

    // app/Models/Riser.php
    // app/Models/Riser.php  — این متدها را اضافه کن
    public function workflowTransfers()
    {
        return $this->hasMany(WorkflowTransfer::class);
    }

    public function latestWorkflowTransfer()
    {
        return $this->hasOne(WorkflowTransfer::class)->latestOfMany('send_date');
    }
}
