<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Zone;
use Spatie\Permission\Traits\HasRoles;


class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'zone_id',
        'role_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }
    


    public function hasPermission($permission)
    {
        if ($this->role && $this->role->name === 'superadmin') {
            return true;
        }
        return $this->role
            && $this->role
            ->permissions()
            ->where('name', $permission)
            ->exists();
    }

    public function operationsToApprove()
    {
        return $this->belongsToMany(
            Operation::class,
            'operation_supervisors'
        )->withPivot([
            'status',
            'comment',
            'approved_at',
            'order'
        ])->withTimestamps();
    }
}
