<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\MaintenanceCase;
use App\Models\Operation;
use App\Models\OperationImage;
use App\Models\Riser;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditLogger
{
    /** @param array<string, mixed>|null $before @param array<string, mixed>|null $after @param array<string, mixed> $metadata */
    public function record(string $event, ?Model $subject = null, ?array $before = null, ?array $after = null, array $metadata = []): AuditLog
    {
        $request = request();
        $actor = $request?->user();
        $operation = match (true) {
            $subject instanceof Operation => $subject,
            $subject instanceof OperationImage => $subject->operation,
            $subject instanceof MaintenanceCase => $subject->operation,
            default => null,
        };
        $riserId = match (true) {
            $subject instanceof Riser => $subject->getKey(),
            $subject instanceof Operation => $subject->riser_id,
            $subject instanceof OperationImage => $subject->riser_id,
            $subject instanceof MaintenanceCase => $subject->riser_id,
            default => null,
        };
        $zoneId = $subject instanceof Zone ? $subject->getKey() : $actor?->zone_id;
        if ($zoneId === null && $riserId !== null) {
            $zoneId = \Illuminate\Support\Facades\DB::table('zone')
                ->whereRaw('ST_Intersects(zone.geom, (SELECT geom FROM riser WHERE id = ?))', [$riserId])
                ->orderBy('zone.id')->value('zone.id');
        }
        $requestId = $request?->attributes->get('_audit_request_id');
        if (! is_string($requestId) || $requestId === '') {
            $provided = $request?->header('X-Request-ID');
            $requestId = is_string($provided) && preg_match('/^[A-Za-z0-9._:-]{1,100}$/', $provided)
                ? $provided
                : (string) Str::uuid();
            $request?->attributes->set('_audit_request_id', $requestId);
        }

        return AuditLog::create([
            'organization_id' => $subject?->organization_id ?? $actor?->organization_id,
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->getRoleNames()->sort()->implode(','),
            'event' => $event,
            'auditable_type' => $subject ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'zone_id' => $zoneId,
            'riser_id' => $riserId,
            'operation_id' => $operation?->getKey() ?? ($subject instanceof Operation ? $subject->getKey() : null),
            'request_id' => $requestId,
            'before' => $this->safePayload($before),
            'after' => $this->safePayload($after),
            'metadata' => $this->safePayload($metadata),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    /** @return array<string, mixed> */
    public function attributesFor(Model $model): array
    {
        return Arr::except($model->getAttributes(), [
            'password', 'remember_token', 'two_factor_secret', 'access_token',
            'token', 'path', 'original_name', 'file', 'content',
        ]);
    }

    private function safePayload(?array $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        $blocked = ['password', 'remember_token', 'two_factor_secret', 'access_token', 'token', 'path', 'original_name', 'file', 'content'];
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), $blocked, true)) {
                unset($payload[$key]);
            } elseif (is_array($value)) {
                $payload[$key] = $this->safePayload($value) ?? [];
            }
        }

        return $payload;
    }
}
