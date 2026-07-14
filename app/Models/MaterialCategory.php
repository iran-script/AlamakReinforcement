<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialCategory extends Model
{
    protected $fillable = [
        'title',
        'code',
        'description',
        'sort',
        'is_active'
    ];

    protected $casts = [
        'is_active'=>'boolean'
    ];

    public function materials()
    {
        return $this->hasMany(Material::class);
    }
}