<?php

namespace App\Models;

use App\Enums\WorkflowVersionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowVersion extends Model
{
    protected $fillable = ['workflow_definition_id', 'version', 'status', 'configuration', 'published_at', 'published_by'];

    protected function casts(): array
    {
        return ['status' => WorkflowVersionStatus::class, 'configuration' => 'array', 'published_at' => 'datetime'];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function states(): HasMany
    {
        return $this->hasMany(WorkflowState::class)->orderBy('sort_order');
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(WorkflowTransition::class)->orderBy('sort_order');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class);
    }

    public function isMutable(): bool
    {
        return $this->status === WorkflowVersionStatus::Draft;
    }
}
