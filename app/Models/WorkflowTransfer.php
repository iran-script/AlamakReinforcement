<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class WorkflowTransfer extends Model
{
    protected $fillable = [
        'user_id_from', 'user_id_to', 'riser_id', 'operation_id',
        'type', 'title', 'comment', 'send_date', 'receive_date',
    ];

    protected $casts = [
        'send_date' => 'datetime',
        'receive_date' => 'datetime',
    ];

    public const TYPE_FLAG_OPERATION      = 'flag_operation_required';
    public const TYPE_LEAK_CHECK_REQUEST  = 'leak_check_request';
    public const TYPE_LEAK_CHECK_REJECTED = 'leak_check_rejected';

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'user_id_from');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'user_id_to');
    }

    public function riser()
    {
        return $this->belongsTo(Riser::class);
    }

    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('receive_date');
    }

    public function scopeInboxFor(Builder $query, int $userId): Builder
    {
        return $query->where('user_id_to', $userId)->whereNull('receive_date');
    }

    public function isPending(): bool
    {
        return is_null($this->receive_date);
    }

    // آیا این ردیف آخرین حلقه‌ی زنجیره‌ست؟ (رسیدگی‌شده و ارجاع‌نخورده = تایید نهایی)
    public function isTerminal(): bool
    {
        if ($this->isPending()) {
            return false;
        }

        return ! static::query()
            ->where('riser_id', $this->riser_id)
            ->when($this->operation_id, fn ($q) => $q->where('operation_id', $this->operation_id))
            ->where('send_date', '>', $this->send_date)
            ->exists();
    }
}