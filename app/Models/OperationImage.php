<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/OperationImage.php
class OperationImage extends Model
{
    protected $table = 'operation_image';

    protected $fillable = [
        'operation_id', 'path', 'original_name', 'uploaded_by','riser_id','type'
    ];

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }
}

