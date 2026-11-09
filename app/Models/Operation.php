<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\OperationCategory;
use App\Models\User;

class Operation extends Model
{
    use SoftDeletes;

    // contract_id تکراری بود، یکیش حذف شد
    protected $fillable = [
        'riser_id',
        'maintenance_case_id', 'client_uuid',
        'group_user_id',
        'contract_id',
        'operation_category_id',
        'user_id',
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

    public function workflowTransfers()
    {
        return $this->hasMany(WorkflowTransfer::class);
    }

    public function images()
    {
        return $this->hasMany(OperationImage::class);
    }

    public function maintenanceCase()
    {
        return $this->belongsTo(MaintenanceCase::class);
    }

    public function groupUser()
    {
        return $this->belongsTo(GroupUser::class, 'group_user_id');
    }
}
