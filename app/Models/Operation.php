<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\OperationCategory;
use App\Models\User;



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

    public function operationCategory()
    {
        return $this->belongsTo(OperationCategory::class, 'operation_category_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function operationMaterial()
    {
        return $this->belongsToMany(
            Material::class,
            'operation_materials'
        )->withPivot([
            'qty'

        ])->withTimestamps();
    }

    public function riser()
    {
        return $this->belongsTo(Riser::class);
    }
}
