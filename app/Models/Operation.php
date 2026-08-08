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
        'contract_id',
        'operation_category_id',
        'user_id',
        'contract_id',
        'operation_date',
        'start_time',
        'end_time',
        'status',
        'priority',
        'total_cost',
        'description',
    ];


    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

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
            'qty',
            'price',
            'total',
            'description'
        ])->withTimestamps();
    }

    public function riser()
    {
        return $this->belongsTo(Riser::class);
    }

    // app/Models/Operation.php
    // app/Models/Operation.php
    public function workflowTransfers()
    {
        return $this->hasMany(WorkflowTransfer::class);
    }

    public function images()
    {
        return $this->hasMany(OperationImage::class);
    }
}
