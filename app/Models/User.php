<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    use HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'email',
        'password',
        'username',
        'zone_id',
        'role_id',
        'group_user_id',
        'contract_id',
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
        return $this->hasRole('superadmin');
    }

    public function hasPermission($permission)
    {
        if ($this->hasRole('superadmin')) {
            return true;
        }

        return $this->can($permission);
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
            'order',
        ])->withTimestamps();
    }

    public function groupUser()
    {
        return $this->belongsTo(GroupUser::class, 'group_user_id');
    }

    public function managedGroups(): BelongsToMany
    {
        return $this->belongsToMany(GroupUser::class, 'boss_managed_groups', 'boss_user_id', 'group_user_id')
            ->withPivot('assigned_by')->withTimestamps();
    }

    /** @return list<int> */
    public function managedGroupIds(): array
    {
        if ($this->isSuperAdmin()) {
            return GroupUser::query()->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        if (! $this->hasRole('boss') || $this->zone_id === null || $this->organization_id === null) {
            return [];
        }

        return $this->managedGroups()
            ->where('group_users.organization_id', $this->organization_id)
            ->where('group_users.zone_id', $this->zone_id)
            ->pluck('group_users.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function managesGroup(int $groupId): bool
    {
        return in_array($groupId, $this->managedGroupIds(), true);
    }

    public function operations()
    {
        return $this->hasMany(Operation::class);
    }

    // app/Models/User.php
    // app/Models/User.php
    public function workflowInbox()
    {
        return $this->hasMany(WorkflowTransfer::class, 'user_id_to')->whereNull('receive_date');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function spatialScopes()
    {
        return $this->belongsToMany(SpatialScope::class, 'user_spatial_scopes')
            ->withPivot('access_level')
            ->withTimestamps();
    }

    public function requestedWorkOrders()
    {
        return $this->hasMany(WorkOrder::class, 'requester_id');
    }

    public function createdWorkOrders()
    {
        return $this->hasMany(WorkOrder::class, 'created_by');
    }

    public function assignedWorkOrders()
    {
        return $this->hasMany(WorkOrder::class, 'assigned_to');
    }

    public function startedWorkflowInstances()
    {
        return $this->hasMany(WorkflowInstance::class, 'started_by');
    }

    public function workflowTasks()
    {
        return $this->hasMany(WorkflowTask::class, 'assigned_to');
    }
}
