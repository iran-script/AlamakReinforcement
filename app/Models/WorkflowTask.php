<?php

namespace App\Models;

use App\Enums\WorkflowTaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTask extends Model
{
    protected $fillable = ['workflow_instance_id', 'workflow_instance_transition_id', 'organization_id', 'assigned_to', 'assigned_role_id', 'status', 'title', 'due_at', 'completed_at', 'metadata'];

    protected function casts(): array
    {
        return ['status' => WorkflowTaskStatus::class, 'due_at' => 'datetime', 'completed_at' => 'datetime', 'metadata' => 'array'];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function instanceTransition(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstanceTransition::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'assigned_role_id');
    }
}
