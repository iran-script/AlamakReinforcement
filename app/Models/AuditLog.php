<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'actor_id', 'actor_role', 'event', 'auditable_type', 'auditable_id',
        'zone_id', 'riser_id', 'operation_id', 'request_id', 'before', 'after', 'metadata',
        'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array', 'metadata' => 'array'];
    }

    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
    public function auditable(): MorphTo { return $this->morphTo(); }
    public function zone(): BelongsTo { return $this->belongsTo(Zone::class); }
    public function riser(): BelongsTo { return $this->belongsTo(Riser::class); }
    public function operation(): BelongsTo { return $this->belongsTo(Operation::class); }
}
