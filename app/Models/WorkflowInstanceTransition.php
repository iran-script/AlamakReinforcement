<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowInstanceTransition extends Model
{
    protected $fillable = ['workflow_instance_id', 'workflow_transition_id', 'from_state_id', 'to_state_id', 'performed_by', 'comment', 'input', 'metadata', 'performed_at'];

    protected function casts(): array
    {
        return ['input' => 'array', 'metadata' => 'array', 'performed_at' => 'datetime'];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function transition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class, 'workflow_transition_id');
    }

    public function fromState(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'from_state_id');
    }

    public function toState(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'to_state_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Workflow history is immutable.'));
        static::deleting(fn () => throw new \LogicException('Workflow history is immutable.'));
    }
}
