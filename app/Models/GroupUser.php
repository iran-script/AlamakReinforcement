<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GroupUser extends Model
{
    protected $table = 'group_users';

    protected $fillable = [
        'name',
        'description',
        'organization_id',
        'zone_id',
    ];


    public function users()
    {
        return $this->hasMany(User::class, 'group_user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'boss_managed_groups', 'group_user_id', 'boss_user_id')
            ->withPivot('assigned_by')->withTimestamps();
    }
}
