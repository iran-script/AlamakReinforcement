<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationSupervisor extends Model
{
    protected $fillable = [
        'operation_id',
        'user_id',
        'status',
        'comment',
        'approved_at',
        'order'
    ];

    protected $casts = [
        'approved_at' => 'datetime'
    ];

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}