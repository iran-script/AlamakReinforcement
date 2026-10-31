<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class AuditLogger
{
    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after @param array<string, mixed> $metadata */
    public function record(string $event, ?Model $subject = null, ?array $before = null, ?array $after = null, array $metadata = []): AuditLog
    {
        $request = request();
        $actor = $request?->user();

        return AuditLog::create([
            'organization_id' => $subject?->organization_id ?? $actor?->organization_id,
            'actor_id' => $actor?->id,
            'event' => $event,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'before' => $before,
            'after' => $after,
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /** @return array<string, mixed> */
    public function attributesFor(Model $model): array
    {
        return Arr::except($model->getAttributes(), ['password', 'remember_token']);
    }
}
