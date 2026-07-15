<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationMaterial extends Model
{
    protected $fillable = [
        'operation_id',
        'material_id',
        'qty',
        'price',
        'total',
        'description',
    ];
}
