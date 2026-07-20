<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    protected $fillable = [
        'material_category_id',
        'title',
        'code',
        'unit',
        'default_price',
        'description',
        'is_active'
    ];

    protected $casts = [
        'default_price' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public function category()
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function operationMaterials()
    {
        return $this->hasMany(OperationMaterial::class);
    }

    public function operations()
    {
        return $this->belongsToMany(
            Operation::class,
            'operation_materials'
        )->withPivot([
            'qty',
            'price',
            'total',
            'description'
        ])->withTimestamps();
    }
}
