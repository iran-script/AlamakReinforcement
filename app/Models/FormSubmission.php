<?php

namespace App\Models;

use App\Enums\FormSubmissionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormSubmission extends Model
{
    protected $fillable = ['organization_id', 'form_version_id', 'subject_type', 'subject_id', 'workflow_instance_id', 'workflow_transition_id', 'status', 'data', 'context', 'submitted_by', 'submitted_at'];

    protected function casts(): array
    {
        return ['status' => FormSubmissionStatus::class, 'data' => 'array', 'context' => 'array', 'submitted_at' => 'datetime'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class);
    }

    public function workflowTransition(): BelongsTo
    {
        return $this->belongsTo(WorkflowTransition::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(FormSubmissionFile::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
