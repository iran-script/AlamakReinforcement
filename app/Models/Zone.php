<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Zone extends Model
{
    protected $table = 'zone';

    protected $fillable = [
        'name',
        'geom',
        'creator_id'
    ];


    public function users()
    {
        return $this->hasMany(User::class);
    }


    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
