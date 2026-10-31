<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowTransition extends Model
{
    protected $fillable = ['workflow_version_id', 'key', 'name', 'from_state_id', 'to_state_id', 'sort_order', 'condition_definition', 'assignment_definition', 'required_permission', 'required_form_key', 'configuration'];

    protected function casts(): array
    {
        return ['condition_definition' => 'array', 'assignment_definition' => 'array', 'configuration' => 'array'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'workflow_version_id');
    }

    public function fromState(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'from_state_id');
    }

    public function toState(): BelongsTo
    {
        return $this->belongsTo(WorkflowState::class, 'to_state_id');
    }
}
