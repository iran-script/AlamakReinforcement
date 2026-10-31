<?php

namespace App\Models;

use App\Enums\WorkflowInstanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowInstance extends Model
{
    protected $fillable = ['organization_id', 'workflow_version_id', 'subject_type', 'subject_id', 'current_state_id', 'status', 'context', 'started_by', 'started_at', 'completed_at'];

    protected function casts(): array
    {
        return ['status' => WorkflowInstanceStatus::class, 'context' => 'array', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function currentState(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'current_state_id');
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function history(): HasMany
    {
        return $this->hasMany(WorkflowInstanceTransition::class)->orderBy('performed_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkflowTask::class);
    }
}
