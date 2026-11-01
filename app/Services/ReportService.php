<?php

namespace App\Services;

use App\Models\Operation;
use App\Models\Riser;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class ReportService
{

    public function generate(Request $request)
    {
        if ($request->input('dataset') === 'maintenance_cases') {
            return $this->generateMaintenanceCases($request);
        }

        $query = $this->operationsQuery($request)->with([

            'riser',
            'user',
            'operationCategory',
            'supervisors',
            'operationMaterial.category',
            'contract',
            'groupUser',
            'maintenanceCase',

        ]);

        $summaryQuery = clone $query;
        $total = (clone $summaryQuery)->reorder()->count('operations.id');
        $operations = $query->paginate(100)->withQueryString();
        $rows = $operations->getCollection();
        $statusCounts = (clone $summaryQuery)->reorder()->select('status')->selectRaw('COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $monthRows = (clone $summaryQuery)->reorder()->whereNotNull('operation_date')
            ->setEagerLoads([])
            ->selectRaw("TO_CHAR(operation_date, 'YYYY-MM') AS label, COUNT(*) AS aggregate")
            ->groupByRaw("TO_CHAR(operation_date, 'YYYY-MM')")
            ->orderBy('label')->get();
        $operationIds = (clone $summaryQuery)->reorder()->select('operations.id');
        $materialUsage = \Illuminate\Support\Facades\DB::table('operation_materials')
            ->join('materials', 'materials.id', '=', 'operation_materials.material_id')
            ->whereIn('operation_materials.operation_id', $operationIds)
            ->select('materials.id as material_id', 'materials.title', 'materials.unit')
            ->selectRaw('SUM(operation_materials.qty) AS qty')
            ->groupBy('materials.id', 'materials.title', 'materials.unit')
            ->orderBy('materials.title')->get()->map(fn ($row) => [
                'material_id' => (int) $row->material_id,
                'title' => $row->title,
                'unit' => $row->unit,
                'qty' => (float) $row->qty,
            ])->all();

        $dataset = $request->input('dataset', 'operations');
        $columnLabels = $this->columnLabels($dataset);
        $selectedColumns = $request->input('columns', array_keys($columnLabels));
        $grouping = $this->groupedSummary($summaryQuery, $request->input('group_by'), $request->user());
        $materialPaginator = null;
        if ($dataset === 'materials') {
            $materialPaginator = \Illuminate\Support\Facades\DB::table('operation_materials')
                ->join('materials', 'materials.id', '=', 'operation_materials.material_id')
                ->whereIn('operation_materials.operation_id', (clone $summaryQuery)->reorder()->select('operations.id'))
                ->select('materials.id as material_id', 'materials.title as material_title', 'materials.unit')
                ->selectRaw('SUM(operation_materials.qty) AS qty')
                ->groupBy('materials.id', 'materials.title', 'materials.unit')
                ->orderBy('materials.title')->orderBy('materials.unit')->paginate(100)->withQueryString();
        }



        return [

            'cards' => [
                'total' => $total,
                'done' => (int) ($statusCounts['approved'] ?? 0),
                'progress' => (int) ($statusCounts['submitOperation'] ?? 0),
                'returned' => (int) ($statusCounts['need_revision'] ?? 0),
                // Preserve the existing stored total_cost reporting field; no new pricing is calculated.
                'total_cost' => (float) (clone $summaryQuery)->reorder()->sum('total_cost'),
            ],

            'dataset' => $dataset,
            'columns' => collect($selectedColumns)->map(fn ($key) => ['key' => $key, 'label' => $columnLabels[$key]])->all(),
            'table' => $dataset === 'materials'
                ? $materialPaginator->getCollection()->map(fn ($row) => array_intersect_key([
                    'material_title' => $row->material_title, 'unit' => $row->unit, 'qty' => (float) $row->qty,
                ], array_flip($selectedColumns)))->all()
                : $this->table($rows, $selectedColumns),
            'grouping' => $grouping,
            'pagination' => [
                'current_page' => ($materialPaginator ?? $operations)->currentPage(),
                'last_page' => ($materialPaginator ?? $operations)->lastPage(),
                'per_page' => ($materialPaginator ?? $operations)->perPage(),
                'total' => ($materialPaginator ?? $operations)->total(),
            ],

            'riserMaterials' => $this->riserMaterialSummary($rows),

            'monthChart' => ['labels' => $monthRows->pluck('label')->all(), 'data' => $monthRows->pluck('aggregate')->map(fn ($v) => (int) $v)->all()],

            'statusChart' => ['labels' => $statusCounts->keys()->all(), 'data' => $statusCounts->values()->map(fn ($v) => (int) $v)->all()],

            'priorityChart' => $this->priorityChart($rows),

            'contractorChart' => $this->contractorChart($rows),

            'materials' => $this->materials($rows),

            // Phase 2 authoritative material report: quantity grouped by unit.
            // Legacy monetary keys remain for backward compatibility only.
            'materialUsage' => $materialUsage,

            'supervisors' => $this->supervisors($rows),

        ];
    }

    public function maintenanceCasesQuery(Request $request): Builder
    {
        $user = $request->user();
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'zone' => ['nullable', 'integer', 'exists:zone,id'],
            'group_user_id' => ['nullable', 'integer', 'exists:group_users,id'],
            'supervisor' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', Rule::in(['open', 'in_progress', 'submitted', 'returned', 'completed'])],
            'case_status' => ['nullable', Rule::in(['open', 'in_progress', 'submitted', 'returned', 'completed'])],
            'priority' => ['nullable', Rule::in(['کم', 'متوسط', 'زیاد', 'فوری'])],
            'columns' => ['sometimes', 'array', 'min:1', 'max:15'],
            'columns.*' => ['string', Rule::in(array_keys($this->columnLabels('maintenance_cases')))],
            'sort' => ['nullable', Rule::in(['identified_at', 'status', 'priority', 'submitted_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'group_by' => ['nullable', Rule::in(['case_status', 'priority', 'zone', 'group', 'supervisor'])],
        ]);

        $repairCount = \Illuminate\Support\Facades\DB::table('operations')->selectRaw('COUNT(*)')
            ->whereColumn('operations.riser_id', 'maintenance_cases.riser_id');
        if (! $user->isSuperAdmin()) {
            $visibleOperationGroups = $user->hasRole('boss') ? $user->managedGroupIds() : array_filter([(int) $user->group_user_id]);
            if ($visibleOperationGroups === []) $repairCount->whereRaw('1 = 0');
            else $repairCount->whereIn('operations.group_user_id', $visibleOperationGroups);
        }

        $query = \App\Models\MaintenanceCase::query()
            ->with(['riser:id,r_giscode,giscode', 'reporter:id,name', 'assignedGroup:id,name', 'lastEditor:id,name', 'operation.operationCategory', 'operationCategory:id,title'])
            ->withCount('photos')
            ->selectSub(\Illuminate\Support\Facades\DB::table('zone')->select('name')
                ->whereRaw('ST_Intersects(zone.geom, (SELECT riser.geom FROM riser WHERE riser.id = maintenance_cases.riser_id))')
                ->orderBy('zone.id')->limit(1), 'zone_name')
            ->selectSub(\Illuminate\Support\Facades\DB::table('zone')
                ->join('organizations', 'organizations.id', '=', 'zone.organization_id')
                ->select('organizations.name')
                ->whereRaw('ST_Intersects(zone.geom, (SELECT riser.geom FROM riser WHERE riser.id = maintenance_cases.riser_id))')
                ->orderBy('zone.id')->limit(1), 'organization_name')
            ->selectSub($repairCount, 'repair_count');

        if (! $user->hasRole('superadmin')) {
            $query->whereHas('riser', fn ($riser) => $riser->visibleTo($user));
            if ($user->hasRole('boss')) {
                $groupIds = $user->managedGroupIds();
                if ($groupIds === []) $query->whereRaw('1 = 0');
                else $query->whereIn('maintenance_cases.assigned_group_id', $groupIds);
            } elseif ($user->group_user_id) {
                $query->where('maintenance_cases.assigned_group_id', $user->group_user_id);
            } else {
                $query->whereRaw('1 = 0');
            }

            if (! empty($validated['zone']) && (int) $validated['zone'] !== (int) $user->zone_id) {
                abort(403, 'Zone خارج از دسترسی شماست.');
            }
            if (! empty($validated['group_user_id'])) {
                $allowedGroupIds = $user->hasRole('boss') ? $user->managedGroupIds() : [(int) $user->group_user_id];
                abort_unless(in_array((int) $validated['group_user_id'], $allowedGroupIds, true), 403, 'گروه کاری خارج از دسترسی شماست.');
            }
            if (! empty($validated['supervisor'])) {
                $allowedGroupIds = $user->hasRole('boss') ? $user->managedGroupIds() : [(int) $user->group_user_id];
                abort_unless(\App\Models\User::query()->whereKey($validated['supervisor'])
                    ->where('zone_id', $user->zone_id)->where('organization_id', $user->organization_id)
                    ->whereIn('group_user_id', $allowedGroupIds)->exists(), 403, 'ناظر خارج از محدوده شماست.');
            }
        }

        if (! empty($validated['from'])) $query->whereDate('identified_at', '>=', $validated['from']);
        if (! empty($validated['to'])) $query->whereDate('identified_at', '<=', $validated['to']);
        $caseStatus = $validated['case_status'] ?? $validated['status'] ?? null;
        if ($caseStatus !== null) $query->where('maintenance_cases.status', $caseStatus);
        if (! empty($validated['priority'])) $query->where('maintenance_cases.priority', $validated['priority']);
        if (! empty($validated['group_user_id'])) $query->where('maintenance_cases.assigned_group_id', $validated['group_user_id']);
        if (! empty($validated['supervisor'])) $query->where('maintenance_cases.reported_by', $validated['supervisor']);
        if (! empty($validated['zone'])) {
            $query->whereHas('riser', fn ($riser) => $riser->whereRaw(
                'ST_Intersects(riser.geom, (SELECT zone.geom FROM zone WHERE zone.id = ?))', [$validated['zone']]
            ));
        }

        $sortColumns = [
            'identified_at' => 'maintenance_cases.identified_at', 'status' => 'maintenance_cases.status',
            'priority' => 'maintenance_cases.priority', 'submitted_at' => 'maintenance_cases.submitted_at',
        ];
        return $query->reorder()->orderBy($sortColumns[$validated['sort'] ?? 'identified_at'], $validated['direction'] ?? 'desc')
            ->orderBy('maintenance_cases.id', $validated['direction'] ?? 'desc');
    }

    private function generateMaintenanceCases(Request $request): array
    {
        $query = $this->maintenanceCasesQuery($request);
        $total = (clone $query)->reorder()->count('maintenance_cases.id');
        $statusCounts = (clone $query)->reorder()->setEagerLoads([])->select([])
            ->selectRaw('maintenance_cases.status, COUNT(*) AS aggregate')->groupBy('maintenance_cases.status')
            ->pluck('aggregate', 'status');
        $paginator = $query->paginate(100)->withQueryString();
        $columnLabels = $this->columnLabels('maintenance_cases');
        $selected = $request->input('columns', array_keys($columnLabels));
        $rows = $paginator->getCollection()->map(function ($case) use ($selected): array {
            $row = [
                'riser' => $case->riser?->r_giscode ?: $case->riser?->giscode,
                'organization' => $case->organization_name,
                'zone' => $case->zone_name,
                'supervisor' => $case->reporter?->name,
                'group' => $case->assignedGroup?->name,
                'identified_at' => $case->identified_at?->format('Y-m-d H:i:s'),
                'description' => $case->description,
                'priority' => $case->priority,
                'status' => $case->status,
                'operation_date' => $case->operation?->operation_date,
                'operation' => $case->operation?->operationCategory?->title ?? $case->operationCategory?->title,
                'submitted_at' => $case->submitted_at?->format('Y-m-d H:i:s'),
                'last_editor' => $case->lastEditor?->name,
                'photos_count' => (int) $case->photos_count,
                'repair_count' => (int) $case->repair_count,
            ];
            return array_intersect_key($row, array_flip($selected));
        })->all();

        $groupBy = $request->input('group_by');
        $groupExpressions = [
            'case_status' => ['maintenance_cases.status', 'maintenance_cases.status'],
            'priority' => ['maintenance_cases.priority', 'maintenance_cases.priority'],
            'group' => ['group_users.name', 'group_users.name'],
            'supervisor' => ['users.name', 'users.name'],
            'zone' => ["(SELECT zone.name FROM zone WHERE ST_Intersects(zone.geom, (SELECT riser.geom FROM riser WHERE riser.id=maintenance_cases.riser_id)) ORDER BY zone.id LIMIT 1)", 'zone_name'],
        ];
        $grouping = [];
        if ($groupBy && isset($groupExpressions[$groupBy])) {
            [$expression, $groupKey] = $groupExpressions[$groupBy];
            $groupQuery = (clone $query)->reorder()->setEagerLoads([])->select([]);
            if ($groupBy === 'group') $groupQuery->leftJoin('group_users', 'group_users.id', '=', 'maintenance_cases.assigned_group_id');
            if ($groupBy === 'supervisor') $groupQuery->leftJoin('users', 'users.id', '=', 'maintenance_cases.reported_by');
            $grouping = $groupQuery->selectRaw($expression.' AS label, COUNT(*) AS count')->groupByRaw($expression)->orderBy('label')->limit(100)->get()
                ->map(fn ($row) => ['label' => (string) ($row->label ?? '—'), 'count' => (int) $row->count])->all();
        }

        return [
            'dataset' => 'maintenance_cases',
            'cards' => [
                'total' => $total,
                'done' => (int) ($statusCounts['completed'] ?? 0),
                'progress' => (int) ($statusCounts['in_progress'] ?? 0),
                'returned' => (int) ($statusCounts['returned'] ?? 0),
                'submitted' => (int) ($statusCounts['submitted'] ?? 0),
                'open' => (int) ($statusCounts['open'] ?? 0),
            ],
            'columns' => collect($selected)->map(fn ($key) => ['key' => $key, 'label' => $columnLabels[$key]])->all(),
            'table' => $rows,
            'grouping' => $grouping,
            'pagination' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
            'materialUsage' => [], 'riserMaterials' => [],
            'monthChart' => ['labels' => [], 'data' => []], 'statusChart' => ['labels' => [], 'data' => []],
            'priorityChart' => ['labels' => [], 'data' => []], 'contractorChart' => ['labels' => [], 'data' => []],
            'materials' => [], 'supervisors' => [],
        ];
    }

    /** Shared, scope-enforced query for preview and exports. */
    public function operationsQuery(Request $request, bool $ignoreDateFilters = false): Builder
    {
        $user = $request->user();
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'string', 'max:100'],
            'operation_category' => ['nullable', 'integer', 'exists:operation_categories,id'],
            'contract' => ['nullable', 'integer', 'exists:contracts,id'],
            'contractor' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor' => ['nullable', 'integer', 'exists:users,id'],
            'group_user_id' => ['nullable', 'integer', 'exists:group_users,id'],
            'case_status' => ['nullable', Rule::in(['open', 'in_progress', 'submitted', 'returned', 'completed'])],
            'material' => ['nullable', 'integer', 'exists:materials,id'],
            'zone' => ['nullable', 'integer', 'exists:zone,id'],
            'dataset' => ['nullable', Rule::in(['operations', 'materials', 'maintenance_cases'])],
            'columns' => ['sometimes', 'array', 'min:1', 'max:15'],
            'columns.*' => ['string', Rule::in(array_keys($this->columnLabels($request->input('dataset', 'operations'))))],
            'sort' => ['nullable', Rule::in(['date', 'status', 'priority'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'group_by' => ['nullable', Rule::in(['status', 'operation', 'priority', 'user', 'zone', 'group', 'supervisor', 'case_status'])],
        ]);

        $query = Operation::query();
        if (! $user->hasRole('superadmin')) {
            $query->whereHas('riser', fn ($riser) => $riser->visibleTo($user));
            if ($user->hasRole('boss')) {
                $managedGroupIds = $user->managedGroupIds();
                if ($managedGroupIds === []) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->whereIn('operations.group_user_id', $managedGroupIds);
                }
            }
            if (! empty($validated['zone']) && (int) $validated['zone'] !== (int) $user->zone_id) {
                abort(403, 'محدوده انتخاب‌شده خارج از دسترسی شماست.');
            }
            if (! empty($validated['group_user_id'])) {
                $allowedGroupIds = $user->hasRole('boss') ? $user->managedGroupIds() : [(int) $user->group_user_id];
                abort_unless(in_array((int) $validated['group_user_id'], $allowedGroupIds, true), 403, 'گروه کاری خارج از دسترسی شماست.');
            }
            if (! empty($validated['supervisor'])) {
                $targetSupervisor = \App\Models\User::query()
                    ->whereHas('roles', fn ($roles) => $roles->where('name', 'supervisor')->where('guard_name', 'web'))
                    ->findOrFail($validated['supervisor']);
                $allowedGroupIds = $user->hasRole('boss') ? $user->managedGroupIds() : [(int) $user->group_user_id];
                abort_unless((int) $targetSupervisor->zone_id === (int) $user->zone_id
                    && (int) $targetSupervisor->organization_id === (int) $user->organization_id
                    && in_array((int) $targetSupervisor->group_user_id, $allowedGroupIds, true), 403);
            }
        }

        if (! $ignoreDateFilters && ! empty($validated['from'])) $query->whereDate('operation_date', '>=', $validated['from']);
        if (! $ignoreDateFilters && ! empty($validated['to'])) $query->whereDate('operation_date', '<=', $validated['to']);
        if (! empty($validated['status'])) $query->where('status', $validated['status']);
        if (! empty($validated['priority'])) $query->where('priority', $validated['priority']);
        if (! empty($validated['operation_category'])) $query->where('operation_category_id', $validated['operation_category']);
        if (! empty($validated['contract'])) $query->where('contract_id', $validated['contract']);
        if (! empty($validated['contractor'])) $query->where('user_id', $validated['contractor']);
        if (! empty($validated['supervisor'])) $query->where('operations.user_id', $validated['supervisor']);
        if (! empty($validated['group_user_id'])) $query->where('operations.group_user_id', $validated['group_user_id']);
        if (! empty($validated['case_status'])) $query->whereHas('maintenanceCase', fn ($case) => $case->where('status', $validated['case_status']));
        if (! empty($validated['material'])) $query->whereHas('operationMaterial', fn ($q) => $q->where('materials.id', $validated['material']));
        if (! empty($validated['zone'])) {
            $query->whereHas('riser', fn ($q) => $q->whereRaw('ST_Intersects(riser.geom_3857, ST_Transform((SELECT geom FROM zone WHERE id = ?), 3857))', [$validated['zone']]));
        }

        $sortColumns = ['date' => 'operations.operation_date', 'status' => 'operations.status', 'priority' => 'operations.priority'];
        $query->reorder()->orderBy($sortColumns[$validated['sort'] ?? 'date'], $validated['direction'] ?? 'desc')
            ->orderBy('operations.id', $validated['direction'] ?? 'desc');

        return $query;
    }

    private function columnLabels(string $dataset = 'operations'): array
    {
        if ($dataset === 'materials') return ['material_title' => 'مصالح', 'unit' => 'واحد', 'qty' => 'مقدار مصرف'];
        if ($dataset === 'maintenance_cases') return [
            'riser' => 'GISCode', 'organization' => 'سازمان', 'zone' => 'Zone جغرافیایی',
            'supervisor' => 'ناظر ثبت‌کننده', 'group' => 'گروه اجرایی', 'identified_at' => 'تاریخ شناسایی',
            'description' => 'شرح خرابی', 'priority' => 'اولویت', 'status' => 'وضعیت پرونده',
            'operation_date' => 'تاریخ تعمیر', 'operation' => 'نوع تعمیر', 'submitted_at' => 'زمان ارسال گزارش',
            'last_editor' => 'آخرین ویرایش‌کننده', 'photos_count' => 'تعداد تصاویر', 'repair_count' => 'دفعات تعمیر علمک',
        ];

        return [
            'riser' => 'کد علمک', 'operation' => 'نوع عملیات', 'date' => 'تاریخ',
            'status' => 'وضعیت', 'priority' => 'اولویت', 'user' => 'ناظر ثبت‌کننده',
            'group' => 'گروه تعمیراتی', 'supervisor' => 'ناظر ثبت‌کننده', 'case_status' => 'وضعیت پرونده تعمیر',
            'cost' => 'مقدار ذخیره‌شده total_cost', 'materials' => 'مصالح (مقدار و واحد)',
        ];
    }

    private function groupedSummary(Builder $query, ?string $groupBy, \App\Models\User $user): array
    {
        if (! $groupBy) return [];

        $groups = match ($groupBy) {
            'status' => (clone $query)->reorder()->setEagerLoads([])->selectRaw('status AS label, COUNT(*) AS count')->groupBy('status')->orderBy('label'),
            'priority' => (clone $query)->reorder()->setEagerLoads([])->selectRaw('priority AS label, COUNT(*) AS count')->groupBy('priority')->orderBy('label'),
            'operation' => (clone $query)->reorder()->setEagerLoads([])->join('operation_categories', 'operation_categories.id', '=', 'operations.operation_category_id')->selectRaw('operation_categories.title AS label, COUNT(*) AS count')->groupBy('operation_categories.title')->orderBy('label'),
            'user' => (clone $query)->reorder()->setEagerLoads([])->join('users', 'users.id', '=', 'operations.user_id')->selectRaw('users.name AS label, COUNT(*) AS count')->groupBy('users.id', 'users.name')->orderBy('label'),
            'supervisor' => (clone $query)->reorder()->setEagerLoads([])->join('users', 'users.id', '=', 'operations.user_id')->selectRaw('users.name AS label, COUNT(*) AS count')->groupBy('users.id', 'users.name')->orderBy('label'),
            'group' => (clone $query)->reorder()->setEagerLoads([])->join('group_users', 'group_users.id', '=', 'operations.group_user_id')->selectRaw('group_users.name AS label, COUNT(*) AS count')->groupBy('group_users.id', 'group_users.name')->orderBy('label'),
            'case_status' => (clone $query)->reorder()->setEagerLoads([])->join('maintenance_cases', 'maintenance_cases.id', '=', 'operations.maintenance_case_id')->selectRaw('maintenance_cases.status AS label, COUNT(*) AS count')->groupBy('maintenance_cases.status')->orderBy('label'),
            'zone' => (clone $query)->reorder()->setEagerLoads([])
                ->join('riser', 'riser.id', '=', 'operations.riser_id')
                ->join('zone', function ($join) { $join->whereRaw('ST_Intersects(zone.geom, riser.geom)'); })
                ->when(! $user->hasRole('superadmin'), fn ($q) => $q->where('zone.id', $user->zone_id))
                ->selectRaw('zone.name AS label, COUNT(DISTINCT operations.id) AS count')
                ->groupBy('zone.id', 'zone.name')->orderBy('label'),
        };

        return $groups->limit(100)->get()->map(fn ($row) => ['label' => (string) ($row->label ?? '—'), 'count' => (int) $row->count])->all();
    }



    private function table($operations, array $selectedColumns)
    {

        $rows = [];


        foreach ($operations as $operation) {


            $row = [


                'riser' => optional($operation->riser)->giscode,


                'operation' => optional($operation->operationCategory)->title,

                'contract_id' => optional($operation->contract)->contractor_name
                    ? optional($operation->contract)->contractor_name . ' - ' . optional($operation->contract)->contract_number
                    : '-',


                'date' => $operation->operation_date,


                'status' => $operation->status,


                'priority' => $operation->priority,


                'user' => optional($operation->user)->name,
                'supervisor' => optional($operation->user)->name,
                'group' => optional($operation->groupUser)->name,
                'case_status' => optional($operation->maintenanceCase)->status,


                'cost' => $operation->total_cost,



                'materials' => $operation->operationMaterial
                    ->map(function ($m) {

                        return [

                            'title' => $m->title,

                            'unit' => $m->unit,

                            'qty' => $m->pivot->qty,

                        ];
                    })

            ];
            $rows[] = array_intersect_key($row, array_flip($selectedColumns));
        }


        return $rows;
    }







    private function riserMaterialSummary($operations)
    {

        $result = [];



        foreach ($operations as $operation) {


            if (!$operation->riser)
                continue;



            $riserId = $operation->riser->id;


            if (!isset($result[$riserId])) {


                $result[$riserId] = [

                    'riser' => $operation->riser->giscode,

                    'materials' => [],

                ];
            }




            foreach ($operation->operationMaterial as $material) {



                $id = $material->id;



                if (!isset($result[$riserId]['materials'][$id])) {


                    $result[$riserId]['materials'][$id] = [

                        'title' => $material->title,

                        'unit' => $material->unit,

                        'qty' => 0

                    ];
                }



                $result[$riserId]['materials'][$id]['qty']
                    += $material->pivot->qty;



            }
        }



        foreach ($result as &$item) {

            $item['materials']
                =
                array_values($item['materials']);
        }



        return array_values($result);
    }






    private function monthChart($operations)
    {

        $data = [];


        foreach ($operations as $operation) {


            if (!$operation->operation_date)
                continue;


            $month =
                Carbon::parse($operation->operation_date)
                ->format('Y-m');



            $data[$month] =
                ($data[$month] ?? 0) + 1;
        }



        ksort($data);



        return [

            'labels' => array_keys($data),

            'data' => array_values($data)

        ];
    }






    private function statusChart($operations)
    {

        $data = [];


        foreach ($operations as $op) {

            $data[$op->status]
                =
                ($data[$op->status] ?? 0) + 1;
        }


        return [

            'labels' => array_keys($data),

            'data' => array_values($data)

        ];
    }






    private function priorityChart($operations)
    {

        $data = [];


        foreach ($operations as $op) {


            $key = $op->priority ?? 'نامشخص';


            $data[$key]
                =
                ($data[$key] ?? 0) + 1;
        }


        return [

            'labels' => array_keys($data),

            'data' => array_values($data)

        ];
    }






    private function contractorChart($operations)
    {

        $data = [];


        foreach ($operations as $op) {


            if (!$op->user)
                continue;


            $name = $op->user->name;


            $data[$name]
                =
                ($data[$name] ?? 0) + 1;
        }


        return [

            'labels' => array_keys($data),

            'data' => array_values($data)

        ];
    }






    private function materials($operations)
    {

        $data = [];


        foreach ($operations as $op) {


            foreach ($op->operationMaterial as $material) {


                $id = $material->id;


                if (!isset($data[$id])) {


                    $data[$id] = [

                        'title' => $material->title,

                        'unit' => $material->unit,

                        'qty' => 0

                    ];
                }



                $data[$id]['qty']
                    += $material->pivot->qty;



            }
        }



        return array_values($data);
    }

    /** @return array<int, array{material_id:int,title:string,unit:string,qty:numeric}> */
    private function materialUsage($operations): array
    {
        $data = [];

        foreach ($operations as $operation) {
            foreach ($operation->operationMaterial as $material) {
                $key = $material->id.'|'.(string) $material->unit;
                $data[$key] ??= [
                    'material_id' => $material->id,
                    'title' => $material->title,
                    'unit' => $material->unit,
                    'qty' => 0,
                ];
                $data[$key]['qty'] += (float) $material->pivot->qty;
            }
        }

        return array_values($data);
    }

    private function supervisors($operations)
    {
        $data = [];


        foreach ($operations as $operation) {

            foreach ($operation->supervisors as $supervisor) {


                $id = $supervisor->id;


                if (!isset($data[$id])) {

                    $data[$id] = [
                        'name' => $supervisor->name,
                        'count' => 0
                    ];
                }


                $data[$id]['count']++;
            }
        }


        return array_values($data);
    }
}
