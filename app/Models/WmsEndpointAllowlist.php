<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WmsEndpointAllowlist extends Model
{
    protected $fillable = ['scheme', 'host', 'port', 'path_prefix', 'resolved_ips', 'status', 'requested_by', 'approved_by', 'approved_at', 'rejected_at', 'decision_note'];

    protected function casts(): array
    {
        return ['port' => 'integer', 'resolved_ips' => 'array', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];
    }

    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class, 'approved_by'); }
}
