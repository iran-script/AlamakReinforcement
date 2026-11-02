<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MaintenanceCase extends Model
{
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'riser_id', 'reported_by', 'assigned_group_id', 'operation_category_id', 'client_uuid',
        'status', 'priority', 'description', 'identified_at', 'closed_at',
        'last_edited_by', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['identified_at' => 'datetime', 'closed_at' => 'datetime', 'submitted_at' => 'datetime'];
    }

    public function riser(): BelongsTo { return $this->belongsTo(Riser::class); }
    public function reporter(): BelongsTo { return $this->belongsTo(User::class, 'reported_by'); }
    public function lastEditor(): BelongsTo { return $this->belongsTo(User::class, 'last_edited_by'); }
    public function assignedGroup(): BelongsTo { return $this->belongsTo(GroupUser::class, 'assigned_group_id'); }
    public function operationCategory(): BelongsTo { return $this->belongsTo(OperationCategory::class); }
    public function operations(): HasMany { return $this->hasMany(Operation::class); }
    /** Backward-compatible accessor for legacy callers; new code must use operations(). */
    public function operation(): HasOne { return $this->hasOne(Operation::class)->latestOfMany(); }
    public function photos(): HasMany { return $this->hasMany(OperationImage::class, 'maintenance_case_id'); }

    public function scopeVisibleTo($query, User $user)
    {
        return $query->whereHas('riser', fn ($riser) => $riser->visibleTo($user));
    }
}
