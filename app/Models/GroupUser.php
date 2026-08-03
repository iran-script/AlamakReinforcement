<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupUser extends Model
{
    protected $table = 'group_users';

    protected $fillable = [
        'name',
        'description',
    ];


    public function users()
    {
        return $this->hasMany(User::class, 'group_user_id');
    }
}