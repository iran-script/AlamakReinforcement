<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;

class Riser extends Model
{
    protected $table = 'riser';

    protected $fillable = [
        'r_giscode',
        'giscode',
        'status',
    ];

    /**
     * فیکس شد:
     * ۱) use Illuminate\Database\Eloquent\Builder اضافه شد (قبلاً نبود => Fatal error موقع صدا زدن scope)
     * ۲) جدول zones (جمع) به zone (مفرد) اصلاح شد — طبق Zone::$table و RiserController@data
     */
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
                (SELECT geom FROM zone WHERE id = ?)
            )
        ", [$user->zone_id]);
    }

    public function workflowTransfers()
    {
        return $this->hasMany(WorkflowTransfer::class);
    }

    public function latestWorkflowTransfer()
    {
        return $this->hasOne(WorkflowTransfer::class)->latestOfMany('send_date');
    }
}
