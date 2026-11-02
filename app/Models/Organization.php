<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = ['code', 'name', 'status', 'settings'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }

    public function groupUsers(): HasMany
    {
        return $this->hasMany(GroupUser::class);
    }

    public function workOrderTypes(): HasMany
    {
        return $this->hasMany(WorkOrderType::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function workflowDefinitions(): HasMany
    {
        return $this->hasMany(WorkflowDefinition::class);
    }

    public function workflowInstances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class);
    }

    public function workflowTasks(): HasMany
    {
        return $this->hasMany(WorkflowTask::class);
    }
}
