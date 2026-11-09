<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\GroupUser;
use App\Models\MaintenanceCase;
use App\Models\WorkflowTransfer;
use Illuminate\Http\Request;

class BossMaintenanceCaseController extends Controller
{
    public function index(Request $request)
    {
        $actor = $request->user();
        $this->authorizeReviewer($actor);
        $groupIds = $this->reviewGroupIds($actor);

        $base = MaintenanceCase::query()
            ->visibleTo($actor)
            ->whereIn('assigned_group_id', $groupIds);

        $stats = [
            'submitted' => (clone $base)->where('status', MaintenanceCase::STATUS_SUBMITTED)->count(),
            'completed' => (clone $base)->where('status', MaintenanceCase::STATUS_COMPLETED)->count(),
            'returned' => (clone $base)->where('status', MaintenanceCase::STATUS_RETURNED)->count(),
        ];

        $status = $request->has('status') ? $request->input('status') : MaintenanceCase::STATUS_SUBMITTED;
        $cases = (clone $base)
            ->with([
                'riser:id,r_giscode,giscode',
                'assignedGroup:id,name',
                'photos:id,maintenance_case_id,operation_id,type',
                'operations:id,maintenance_case_id,status',
            ])
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = trim((string) $request->input('q'));
                $query->where(function ($search) use ($term): void {
                    $search->whereKey(is_numeric($term) ? (int) $term : -1)
                        ->orWhereHas('riser', fn ($riser) => $riser
                            ->where('r_giscode', 'ilike', '%'.$term.'%')
                            ->orWhere('giscode', 'ilike', '%'.$term.'%'));
                });
            })
            ->when($request->filled('group') && in_array((int) $request->input('group'), $groupIds, true), fn ($query) => $query->where('assigned_group_id', (int) $request->input('group')))
            ->when($status && in_array($status, ['submitted', 'returned', 'completed'], true), fn ($query) => $query->where('status', $status))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('submitted_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('submitted_at', '<=', $request->date('to')))
            ->orderByDesc('submitted_at')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $groups = \App\Models\GroupUser::query()->whereIn('id', $groupIds)->orderBy('name')->get(['id', 'name']);

        return view('boss.maintenance-cases.index', compact('cases', 'stats', 'groups'));
    }

    public function show(Request $request, MaintenanceCase $maintenanceCase)
    {
        $actor = $request->user();
        $this->authorizeReviewer($actor);
        $this->authorizeCase($actor, $maintenanceCase);

        $case = $maintenanceCase->load([
            'riser:id,r_giscode,giscode', 'reporter:id,name', 'assignedGroup:id,name', 'lastEditor:id,name',
            'photos:id,maintenance_case_id,operation_id,riser_id,type,path,original_name,uploaded_by',
            'operations.operationCategory', 'operations.operationMaterial', 'operations.images',
        ]);
        $operationIds = $case->operations->modelKeys();
        $auditLogs = AuditLog::query()->with('actor:id,name')
            ->where(fn ($query) => $query
                ->where(fn ($caseQuery) => $caseQuery->where('auditable_type', MaintenanceCase::class)->where('auditable_id', $case->id))
                ->orWhereIn('operation_id', $operationIds))
            ->latest('created_at')->latest('id')->limit(160)->get();
        $workflowTransfers = WorkflowTransfer::query()->with(['fromUser:id,name', 'toUser:id,name'])
            ->where('riser_id', $case->riser_id)
            ->where(fn ($query) => $query->whereIn('operation_id', $operationIds)->orWhereNull('operation_id'))
            ->latest('send_date')->latest('id')->limit(100)->get();
        $timeline = collect()
            ->merge($auditLogs->map(fn (AuditLog $log): array => ['at' => $log->created_at, 'title' => $log->event, 'actor' => $log->actor?->name, 'detail' => $log->metadata]))
            ->merge($workflowTransfers->map(fn (WorkflowTransfer $transfer): array => ['at' => $transfer->send_date, 'title' => $transfer->title ?: $transfer->type, 'actor' => $transfer->fromUser?->name, 'detail' => $transfer->comment]))
            ->sortByDesc(fn (array $item): int => $item['at']?->timestamp ?? 0)->values();

        $canFinalReview = $case->status === MaintenanceCase::STATUS_SUBMITTED && $case->operations->isNotEmpty();

        return view('boss.maintenance-cases.show', [
            'case' => $case,
            'timeline' => $timeline,
            'canFinalReview' => $canFinalReview,
        ]);
    }

    private function authorizeReviewer($actor): void
    {
        abort_unless($actor && $actor->hasRole('boss') && $actor->can('operation.approve'), 403, 'دسترسی تأیید تعمیرات برای شما مجاز نیست.');
        abort_unless($actor->zone_id !== null && $actor->organization_id !== null && $actor->managedGroupIds() !== [], 403, 'محدوده مدیریتی معتبر برای تأیید تعمیرات یافت نشد.');
    }

    private function authorizeCase($actor, MaintenanceCase $case): void
    {
        abort_unless($actor->managesGroup((int) $case->assigned_group_id), 403, 'این پرونده خارج از گروه‌های مجاز شماست.');
        abort_unless(\App\Models\Riser::query()->visibleTo($actor)->whereKey($case->riser_id)->exists(), 404);
    }

    private function reviewGroupIds($actor): array
    {
        $ids = $actor->managedGroupIds();
        if ($actor->group_user_id && GroupUser::query()
            ->whereKey($actor->group_user_id)
            ->where('organization_id', $actor->organization_id)
            ->where('zone_id', $actor->zone_id)
            ->exists()) {
            $ids[] = (int) $actor->group_user_id;
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
