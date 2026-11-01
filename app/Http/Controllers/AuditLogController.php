<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'actor_id' => ['nullable', 'integer', 'exists:users,id'],
            'actor' => ['nullable', 'string', 'max:150'],
            'riser_id' => ['nullable', 'integer', 'exists:riser,id'],
            'riser' => ['nullable', 'string', 'max:150'],
            'operation_id' => ['nullable', 'integer', 'exists:operations,id'],
            'zone_id' => ['nullable', 'integer', 'exists:zone,id'],
            'event' => ['nullable', 'string', 'max:100'],
        ]);

        $logs = AuditLog::query()->with(['actor:id,name,username', 'zone:id,name', 'riser:id,r_giscode', 'operation:id,status'])
            ->when($filters['from'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->when($filters['actor_id'] ?? null, fn ($query, $id) => $query->where('actor_id', $id))
            ->when($filters['actor'] ?? null, fn ($query, $term) => $query->whereHas('actor', fn ($actor) => $actor
                ->where('name', 'ilike', '%'.$term.'%')->orWhere('username', 'ilike', '%'.$term.'%')))
            ->when($filters['riser_id'] ?? null, fn ($query, $id) => $query->where('riser_id', $id))
            ->when($filters['riser'] ?? null, fn ($query, $term) => $query->whereHas('riser', fn ($riser) => $riser
                ->where('r_giscode', 'ilike', '%'.$term.'%')->orWhere('giscode', 'ilike', '%'.$term.'%')))
            ->when($filters['operation_id'] ?? null, fn ($query, $id) => $query->where('operation_id', $id))
            ->when($filters['zone_id'] ?? null, fn ($query, $id) => $query->where('zone_id', $id))
            ->when($filters['event'] ?? null, fn ($query, $event) => $query->where('event', 'ilike', '%'.$event.'%'))
            ->latest('created_at')->latest('id')->paginate(50)->withQueryString();

        return view('audit.index', compact('logs', 'filters'));
    }
}
