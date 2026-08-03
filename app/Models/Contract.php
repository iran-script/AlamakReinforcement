<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    protected $fillable = [
        'contractor_name',
        'contract_number',
        'supervisor_id',
        'creator_id',
    ];


    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }


    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function operations()
    {
        return $this->hasMany(Operation::class);
    }
}
