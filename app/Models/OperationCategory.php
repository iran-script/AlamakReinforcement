<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationCategory extends Model
{
    protected $fillable = [
        'title',
        'code',
        'description',
        'sort',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function operations()
    {
        return $this->hasMany(Operation::class);
    }
}