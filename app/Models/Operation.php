<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Operation extends Model
{
    //

    protected $fillable = [
        'riser_id',
        'operation_category_id',
        'user_id',
        'contractor_id',
        'operation_date',
        'start_time',
        'end_time',
        'status',
        'priority',
        'total_cost',
        'description',
    ];

    public function supervisors()
    {
        return $this->belongsToMany(
            User::class,
            'operation_supervisors'
        )->withPivot([
            'status',
            'comment',
            'approved_at',
            'order'
        ])->withTimestamps();
    }
}
