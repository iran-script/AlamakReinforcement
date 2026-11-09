<?php

namespace App\Http\Controllers;

use App\Models\Riser;
use App\Models\Operation;
use App\Models\User;
use App\Models\Zone;
use App\Models\Contract;
use App\Models\Material;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{

    public function statistics(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'compare1_from' => ['nullable', 'date'], 'compare1_to' => ['nullable', 'date', 'after_or_equal:compare1_from'],
            'compare2_from' => ['nullable', 'date'], 'compare2_to' => ['nullable', 'date', 'after_or_equal:compare2_from'],
        ]);
        $reportQuery = app(\App\Services\ReportService::class);
        $query = $reportQuery->operationsQuery($request);
        $comparisonBase = $reportQuery->operationsQuery($request, ignoreDateFilters: true);
        $statuses = (clone $query)->reorder()->select('status')->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('status')->pluck('aggregate', 'status');
        $months = (clone $query)->reorder()->whereNotNull('operation_date')
            ->selectRaw("TO_CHAR(operation_date, 'YYYY-MM') AS label, COUNT(*) AS aggregate")
            ->groupByRaw("TO_CHAR(operation_date, 'YYYY-MM')")
            ->orderBy('label')->get();

        $ids = (clone $query)->reorder()->select('operations.id');
        $base = fn () => (clone $query)->reorder();
        $by = fn ($rows, string $label, string $value = 'aggregate') => [
            'labels' => $rows->pluck($label)->all(),
            'data' => $rows->pluck($value)->map(fn ($v) => (int) $v)->all(),
        ];
        $types = $base()->join('operation_categories', 'operation_categories.id', '=', 'operations.operation_category_id')
            ->select('operation_categories.title as label')->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('operation_categories.title')->orderByDesc('aggregate')->get();
        $executors = $base()->join('group_users', 'group_users.id', '=', 'operations.group_user_id')
            ->select('group_users.name as label')->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('group_users.id', 'group_users.name')->orderByDesc('aggregate')->limit(20)->get();
        $supervisors = $base()->join('users', 'users.id', '=', 'operations.user_id')
            ->select('users.name as label')->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('users.id', 'users.name')->orderByDesc('aggregate')->limit(20)->get();
        $materials = \Illuminate\Support\Facades\DB::table('operation_materials')
            ->join('materials', 'materials.id', '=', 'operation_materials.material_id')
            ->whereIn('operation_materials.operation_id', $ids)
            ->selectRaw("materials.title || ' (' || COALESCE(materials.unit, '') || ')' AS label")
            ->selectRaw('SUM(operation_materials.qty) AS aggregate')
            ->groupBy('materials.title', 'materials.unit')->orderByDesc('aggregate')->limit(30)->get();
        $topRisers = $base()->join('riser', 'riser.id', '=', 'operations.riser_id')
            ->selectRaw("COALESCE(riser.giscode, riser.id::text) AS label")
            ->selectRaw('COUNT(*) AS aggregate')->groupBy('riser.id', 'riser.giscode')
            ->havingRaw('COUNT(*) > 1')->orderByDesc('aggregate')->limit(20)->get();
        $zoneQuery = $base()->join('riser', 'riser.id', '=', 'operations.riser_id')
            ->join('zone', function ($join) {
                $join->whereRaw('ST_Intersects(zone.geom, riser.geom)');
            });
        if (! $request->user()->hasRole('superadmin')) {
            if (! $request->user()->isGlobalMaintenanceViewer()) {
                $zoneQuery->where('zone.id', $request->user()->zone_id);
            }
        }
        $zones = $zoneQuery->select('zone.name as label')->selectRaw('COUNT(DISTINCT operations.id) AS aggregate')
            ->groupBy('zone.id', 'zone.name')->orderByDesc('aggregate')->get();
        $pendingByZone = (clone $zoneQuery)->join('operation_supervisors', 'operation_supervisors.operation_id', '=', 'operations.id')
            ->where('operation_supervisors.status', 'pending')
            ->select('zone.name as label')->selectRaw('COUNT(DISTINCT operations.id) AS aggregate')
            ->groupBy('zone.id', 'zone.name')->orderByDesc('aggregate')->get();
        $materialByZone = \Illuminate\Support\Facades\DB::table('operation_materials')
            ->join('operations', 'operations.id', '=', 'operation_materials.operation_id')
            ->whereNull('operations.deleted_at')
            ->join('riser', 'riser.id', '=', 'operations.riser_id')
            ->join('zone', function ($join) { $join->whereRaw('ST_Intersects(zone.geom, riser.geom)'); })
            ->join('materials', 'materials.id', '=', 'operation_materials.material_id')
            ->whereIn('operations.id', $ids);
        if (! $request->user()->hasRole('superadmin') && ! $request->user()->isGlobalMaintenanceViewer()) {
            $materialByZone->where('zone.id', $request->user()->zone_id);
        }
        $materialByZone = $materialByZone->selectRaw("zone.name || ' — ' || materials.title || ' (' || COALESCE(materials.unit, '') || ')' AS label")
            ->selectRaw('SUM(operation_materials.qty) AS aggregate')
            ->groupBy('zone.id', 'zone.name', 'materials.id', 'materials.title', 'materials.unit')
            ->orderByDesc('aggregate')->limit(50)->get();
        $repeatRisers = (clone $query)->reorder()->whereNotNull('riser_id')->select('riser_id')
            ->groupBy('riser_id')->havingRaw('COUNT(*) > 1')->getQuery();
        $approvalTrend = $base()->whereIn('status', ['approved', 'need_revision'])
            ->selectRaw("TO_CHAR(operation_date, 'YYYY-MM') AS label")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'approved') AS approved")
            ->selectRaw("COUNT(*) FILTER (WHERE status = 'need_revision') AS returned")
            ->groupByRaw("TO_CHAR(operation_date, 'YYYY-MM')")->orderBy('label')->get();

        $comparisons = [];
        foreach ([1, 2] as $period) {
            $start = $request->input("compare{$period}_from");
            $end = $request->input("compare{$period}_to");
            if ($start && $end && strtotime($start) && strtotime($end) && $start <= $end) {
                $comparisons[] = ['label' => $start.' — '.$end, 'count' => (int) (clone $comparisonBase)->whereBetween('operation_date', [$start, $end])->count()];
            }
        }

        $risers = \App\Models\Riser::query()->visibleTo($request->user());
        if ($request->filled('zone')) {
            $risers->whereRaw('ST_Intersects(riser.geom, (SELECT geom FROM zone WHERE id = ?))', [$request->input('zone')]);
        }
        $riserStatuses = (clone $risers)->reorder()->whereNotNull('status')->select('status as label')->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('status')->orderBy('status')->get();
        $repeatRiserCount = \Illuminate\Support\Facades\DB::query()->fromSub($repeatRisers, 'repeated_risers')->count();

        return response()->json([
            'total' => (clone $query)->count('operations.id'),
            'total_risers' => $risers->count(),
            'repaired_risers' => (clone $query)->whereNotNull('riser_id')->distinct()->count('riser_id'),
            'approved_risers' => (clone $query)->where('status', 'approved')->whereNotNull('riser_id')->distinct()->count('riser_id'),
            'today_operations' => $base()->whereDate('operations.created_at', today())->count(),
            'approved_operations' => (clone $query)->where('status', 'approved')->count(),
            'returned_operations' => (clone $query)->where('status', 'need_revision')->count(),
            'pending_approval' => (clone $query)->whereHas('supervisors', fn ($q) => $q->where('operation_supervisors.status', 'pending'))->distinct()->count('operations.id'),
            'repeated_risers' => $repeatRiserCount,
            'active_executors' => $base()->whereNotNull('group_user_id')->distinct()->count('group_user_id'),
            'active_supervisors' => $base()->whereNotNull('user_id')->distinct()->count('user_id'),
            'status' => ['labels' => $statuses->keys()->all(), 'data' => $statuses->values()->map(fn ($v) => (int) $v)->all()],
            'monthly' => ['labels' => $months->pluck('label')->all(), 'data' => $months->pluck('aggregate')->map(fn ($v) => (int) $v)->all()],
            'operation_types' => $by($types, 'label'), 'zones' => $by($zones, 'label'),
            'pending_by_zone' => $by($pendingByZone, 'label'), 'materials_by_zone' => ['labels' => $materialByZone->pluck('label')->all(), 'data' => $materialByZone->pluck('aggregate')->map(fn ($v) => (float) $v)->all()],
            'riser_statuses' => $by($riserStatuses, 'label'),
            'executors' => $by($executors, 'label'), 'supervisors' => $by($supervisors, 'label'),
            'materials' => ['labels' => $materials->pluck('label')->all(), 'data' => $materials->pluck('aggregate')->map(fn ($v) => (float) $v)->all()],
            'top_risers' => $by($topRisers, 'label'),
            'approval_trend' => [
                'labels' => $approvalTrend->pluck('label')->all(),
                'approved' => $approvalTrend->pluck('approved')->map(fn ($v) => (int) $v)->all(),
                'returned' => $approvalTrend->pluck('returned')->map(fn ($v) => (int) $v)->all(),
            ],
            'comparisons' => $comparisons,
        ]);
    }

    public function index(\Illuminate\Http\Request $request)
    {
        $user = $request->user();
        $visibleOperations = function ($query) use ($user) {
            $query->whereHas('riser', fn ($riser) => $riser->visibleTo($user));
            if ($user->hasRole('boss')) {
                $groupIds = $user->managedGroupIds();
                if ($groupIds === []) $query->whereRaw('1 = 0');
                else $query->whereIn('operations.group_user_id', $groupIds);
            }
            return $query;
        };
        $operations = Operation::query()->where($visibleOperations);
        $visibleOperationIds = Operation::query()->where($visibleOperations)->select('operations.id');


        // =====================
        // علمک‌ها
        // =====================

        $contract = Contract::query()
            ->whereHas('operations', $visibleOperations)
            ->withCount(['operations' => $visibleOperations])
        ->orderByDesc('operations_count')
            ->limit(10)
            ->get();



        // =====================
        // کاربران
        // =====================


        // کاربرانی که عملیات ثبت کرده‌اند

        $userStats = User::withCount(['operations' => $visibleOperations])
            ->orderByDesc('operations_count')
            ->limit(10)
            ->get();

        $dashboardZones = $user->hasRole('superadmin')
            ? Zone::query()->orderBy('name')->get(['id', 'name'])
            : ($user->isGlobalMaintenanceViewer()
                ? Zone::query()->orderBy('name')->get(['id', 'name'])
                : Zone::query()->whereKey($user->zone_id)->get(['id', 'name']));
        $dashboardCategories = \App\Models\OperationCategory::query()
            ->whereHas('operations', $visibleOperations)->orderBy('title')->get(['id', 'title']);
        $dashboardStatuses = (clone $operations)->whereNotNull('status')->distinct()->orderBy('status')->pluck('status');
        $dashboardUsers = User::query()->whereHas('roles', fn ($roles) => $roles->where('name', 'supervisor')->where('guard_name', 'web'))
            ->whereIn('id', (clone $visibleOperationIds)->whereNotNull('user_id')->select('user_id'))
            ->orderBy('name')->get(['id', 'name']);
        $dashboardSupervisors = $dashboardUsers;
        $dashboardGroups = \App\Models\GroupUser::query()
            ->whereIn('id', (clone $visibleOperationIds)->whereNotNull('group_user_id')->select('group_user_id'))
            ->orderBy('name')->get(['id', 'name']);


        // =====================
        // اقلام مصرفی
        // =====================

        // تعداد کل اقلام مصرف شده
        $topMaterials = Material::select(
            'materials.id',
            'materials.title',
            'materials.unit'
        )
            ->join(
                'operation_materials',
                'materials.id',
                '=',
                'operation_materials.material_id'
            )
            ->whereIn('operation_materials.operation_id', $visibleOperationIds)
            ->selectRaw('SUM(operation_materials.qty) as total_qty')
            ->groupBy(
                'materials.id',
                'materials.title',
                'materials.unit'
            )
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();




        // =====================
        // زون ها
        // =====================

        $latestOperations = Operation::with([

            'riser',

            'operationCategory',

            'user'

        ])->where($visibleOperations)

            ->latest()

            ->limit(8)

            ->get();

        $maintenanceApprovalStats = null;
        if ($user->hasRole('boss') && $user->can('operation.approve')) {
            $approvalCases = \App\Models\MaintenanceCase::query()
                ->visibleTo($user)
                ->whereIn('assigned_group_id', $user->managedGroupIds());
            $maintenanceApprovalStats = [
                'submitted' => (clone $approvalCases)->where('status', \App\Models\MaintenanceCase::STATUS_SUBMITTED)->count(),
                'completed' => (clone $approvalCases)->where('status', \App\Models\MaintenanceCase::STATUS_COMPLETED)->count(),
                'returned' => (clone $approvalCases)->where('status', \App\Models\MaintenanceCase::STATUS_RETURNED)->count(),
            ];
        }

        $maintenanceReturnedCount = null;
        if ($user->hasRole('supervisor') && $user->can('maintenance.case.view') && $user->group_user_id) {
            $maintenanceReturnedCount = \App\Models\MaintenanceCase::query()
                ->visibleTo($user)
                ->where('assigned_group_id', (int) $user->group_user_id)
                ->where('status', \App\Models\MaintenanceCase::STATUS_RETURNED)
                ->count();
        }






        return view('dashboard', compact(










            'userStats',
            'contract',



            'latestOperations',

            
            'topMaterials', 'maintenanceApprovalStats', 'maintenanceReturnedCount'

            , 'dashboardZones', 'dashboardCategories', 'dashboardStatuses', 'dashboardUsers', 'dashboardSupervisors', 'dashboardGroups'


        ));
    }
}
