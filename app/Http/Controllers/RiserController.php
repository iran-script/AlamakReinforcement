<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Riser;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\Auth;
use App\Models\WorkflowTransfer;
use App\Models\MaintenanceCase;
use App\Models\AuditLog;
use App\Services\MapProfileService;
use Illuminate\Validation\Rule;


class RiserController extends Controller
{
    // app/Http/Controllers/RiserController.php

    public function __construct(
        private \App\Services\WorkflowService $workflow,
        private \App\Services\AuditLogger $audit,
    ) {}

    public function flagOperationRequired(Request $request, Riser $riser)
    {
        abort_unless($request->user()->hasRole('supervisor') || $request->user()->can('flag-riser-operation'), 403);
        $riser = Riser::query()->visibleTo($request->user())->findOrFail($riser->id);

        $transfer = $this->workflow->flagRiser($riser, $request->user(), $request->input('comment'));
        $this->audit->record('riser.flagged_for_operation', $riser, null, [
            'riser_id' => $riser->id, 'workflow_transfer_id' => $transfer->id,
        ]);

        return response()->json(['success' => true, 'transfer' => $transfer]);
    }
    public function index(Request $request)
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'code_state' => ['nullable', Rule::in(['has_code', 'no_code'])],
            'sort' => ['nullable', Rule::in(['id', 'r_giscode', 'giscode', 'status', 'created_at', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([25, 50, 100])],
        ]);

        $user = $request->user();
        $visibleQuery = Riser::query()->visibleTo($user);
        $query = (clone $visibleQuery)
            ->select(['id', 'r_giscode', 'giscode', 'status', 'created_at', 'updated_at']);

        $search = trim((string) ($validated['q'] ?? ''));
        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery->where('r_giscode', 'ilike', '%'.$search.'%')
                    ->orWhere('giscode', 'ilike', '%'.$search.'%');

                if (ctype_digit($search)) {
                    $searchQuery->orWhere('id', (int) $search);
                }
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (($validated['code_state'] ?? null) === 'has_code') {
            $query->where(fn ($codeQuery) => $codeQuery
                ->whereNotNull('r_giscode')->where('r_giscode', '<>', '')
                ->orWhere(fn ($secondCodeQuery) => $secondCodeQuery
                    ->whereNotNull('giscode')->where('giscode', '<>', '')));
        } elseif (($validated['code_state'] ?? null) === 'no_code') {
            $query->where(function ($codeQuery): void {
                $codeQuery->whereNull('r_giscode')->orWhere('r_giscode', '');
                $codeQuery->where(function ($secondCodeQuery): void {
                    $secondCodeQuery->whereNull('giscode')->orWhere('giscode', '');
                });
            });
        }

        $sort = $validated['sort'] ?? 'updated_at';
        $direction = $validated['direction'] ?? 'desc';
        $perPage = (int) ($validated['per_page'] ?? 25);

        $statuses = (clone $visibleQuery)
            ->whereNotNull('status')
            ->where('status', '<>', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status');

        $totalVisible = (clone $visibleQuery)->reorder()->count('riser.id');
        $withCode = (clone $visibleQuery)->reorder()->where(function ($codeQuery): void {
            $codeQuery->whereNotNull('r_giscode')->where('r_giscode', '<>', '')
                ->orWhere(fn ($secondCodeQuery) => $secondCodeQuery
                    ->whereNotNull('giscode')->where('giscode', '<>', ''));
        })->count('riser.id');

        $risers = $query->orderBy($sort, $direction)
            ->orderBy('id', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return view('riser.index', [
            'risers' => $risers,
            'statuses' => $statuses,
            'totalVisible' => $totalVisible,
            'withCode' => $withCode,
            'perPage' => $perPage,
            'filters' => $validated,
        ]);
    }

    /**
     * نمایش صفحه
     */
    public function show($id, MapProfileService $profiles)
    {
        $user = request()->user();
        $riser = Riser::query()->visibleTo($user)->findOrFail($id);
        $returnTo = request()->query('return_to');
        if (! is_string($returnTo) || ! str_starts_with($returnTo, url('/'))) {
            $returnTo = route('riserIndex');
        }

        $caseQuery = MaintenanceCase::query()
            ->where('riser_id', $riser->id)
            ->with([
                'riser:id,r_giscode,giscode', 'reporter:id,name', 'assignedGroup:id,name', 'lastEditor:id,name',
                'photos:id,maintenance_case_id,operation_id,riser_id,type,path,original_name,uploaded_by',
                'operations.operationCategory', 'operations.operationMaterial', 'operations.images',
            ])
            ->latest('identified_at')->latest('id');

        if (! $user->isSuperAdmin() && ! $user->isGlobalMaintenanceViewer()) {
            $caseQuery->when($user->hasRole('boss'),
                fn ($query) => $query->whereIn('assigned_group_id', $user->managedGroupIds()),
                fn ($query) => $query->where('assigned_group_id', $user->group_user_id));
        }

        $maintenanceCases = $caseQuery->get();
        $currentStatuses = [MaintenanceCase::STATUS_OPEN, MaintenanceCase::STATUS_IN_PROGRESS, MaintenanceCase::STATUS_SUBMITTED, MaintenanceCase::STATUS_RETURNED];
        $maintenanceCase = $maintenanceCases->first(fn (MaintenanceCase $case): bool => in_array($case->status, $currentStatuses, true));
        $historicalCases = $maintenanceCases->reject(fn (MaintenanceCase $case): bool => $maintenanceCase && $case->id === $maintenanceCase->id)->values();
        $timeline = collect();

        if ($maintenanceCase) {
            $operationIds = $maintenanceCase->operations->modelKeys();
            $auditLogs = AuditLog::query()->with('actor:id,name')
                ->where(function ($query) use ($maintenanceCase, $operationIds): void {
                    $query->where(fn ($caseQuery) => $caseQuery->where('auditable_type', MaintenanceCase::class)->where('auditable_id', $maintenanceCase->id))
                        ->orWhereIn('operation_id', $operationIds);
                })->latest('created_at')->latest('id')->limit(120)->get();
            $workflowTransfers = WorkflowTransfer::query()->with(['fromUser:id,name', 'toUser:id,name'])
                ->where('riser_id', $riser->id)
                ->where(fn ($query) => $query->whereIn('operation_id', $operationIds)->orWhereNull('operation_id'))
                ->latest('send_date')->latest('id')->limit(80)->get();
            $timeline = collect()
                ->merge($auditLogs->map(fn (AuditLog $log): array => ['at' => $log->created_at, 'title' => $log->event, 'actor' => $log->actor?->name, 'detail' => $log->metadata]))
                ->merge($workflowTransfers->map(fn (WorkflowTransfer $transfer): array => ['at' => $transfer->send_date, 'title' => $transfer->title ?: $transfer->type, 'actor' => $transfer->fromUser?->name, 'detail' => $transfer->comment]))
                ->sortByDesc(fn (array $item): int => $item['at']?->timestamp ?? 0)->values();
        }

        $canEditOperations = $maintenanceCase && $user->hasRole('supervisor')
            && (int) $maintenanceCase->assigned_group_id === (int) $user->group_user_id
            && $maintenanceCase->canAcceptOperations();
        $canSubmitOperations = $canEditOperations && $maintenanceCase->operations->isNotEmpty();
        $canReview = $maintenanceCase && $user->hasRole('boss') && $user->can('operation.approve')
            && $user->managesGroup((int) $maintenanceCase->assigned_group_id)
            && $maintenanceCase->status === MaintenanceCase::STATUS_SUBMITTED;
        $canEditPhotos = $maintenanceCase && ($user->isSuperAdmin()
            || ($user->hasRole('boss') && $user->can('operation.edit') && $user->managesGroup((int) $maintenanceCase->assigned_group_id))
            || ($user->hasRole('supervisor') && (int) $user->group_user_id === (int) $maintenanceCase->assigned_group_id && $maintenanceCase->canAcceptOperations()));

        return view('riser.show', [
            'id' => $id,
            'riser' => $riser,
            'maintenanceCase' => $maintenanceCase,
            'maintenanceCases' => $maintenanceCases,
            'historicalCases' => $historicalCases,
            'timeline' => $timeline,
            'canEditOperations' => (bool) $canEditOperations,
            'canSubmitOperations' => (bool) $canSubmitOperations,
            'canReview' => (bool) $canReview,
            'canEditPhotos' => (bool) $canEditPhotos,
            'baseMap' => $profiles->payloadForView($user),
            'hasActiveMapProfile' => $profiles->activeFor($user) !== null,
            'returnTo' => $returnTo,
        ]);
    }


    /**
     * اطلاعات کامل علمک
     */
    public function details($id)
    {
        /*
        |--------------------------------------------------------------------------
        | چک دسترسی مکانی — قبلاً این‌جا نبود، اضافه شد (هم برای وب هم موبایل).
        | بدون این، هر کاربر لاگین‌شده با حدس زدن id می‌تونست جزئیات رایزر
        | یک Zone دیگه رو ببینه.
        |--------------------------------------------------------------------------
        */
        $currentUser = auth()->user();

        if (! $currentUser) {
            abort(401, 'ابتدا وارد حساب کاربری شوید.');
        }

        if (! Riser::visibleTo($currentUser)->where('id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'دسترسی به این علمک نداری.'
            ], 403);
        }

        /*
        |--------------------------------------------------------------------------
        | اطلاعات اصلی علمک
        |--------------------------------------------------------------------------
        */

        $riser = DB::table('riser')
            ->select('id', 'r_giscode', 'giscode', 'geom', 'updated_at', 'status')
            ->selectRaw('ST_Asgeojson(ST_Transform(geom, 4326)) as wkt')
            ->where('id', $id)
            ->first();




        if (!$riser) {

            return response()->json([
                'success' => false,
                'message' => 'علمک پیدا نشد.'
            ], 404);
        }

        $zone = DB::table('zone')
            ->leftJoin('organizations', 'organizations.id', '=', 'zone.organization_id')
            ->whereRaw('ST_Intersects(geom, (SELECT geom FROM riser WHERE id = ?))', [$id])
            ->first(['zone.id', 'zone.name', 'zone.organization_id', 'organizations.name as organization_name']);

        $caseQuery = MaintenanceCase::query()
            ->where('riser_id', $id)
            ->with(['photos' => fn ($query) => $query->whereNull('operation_id')->select('id', 'maintenance_case_id', 'operation_id', 'riser_id', 'type', 'path', 'original_name', 'uploaded_by')]);
        if (! $currentUser->isSuperAdmin() && ! $currentUser->isGlobalMaintenanceViewer()) {
            $caseQuery->when($currentUser->hasRole('boss'),
                fn ($query) => $query->whereIn('assigned_group_id', $currentUser->managedGroupIds()),
                fn ($query) => $query->where('assigned_group_id', $currentUser->group_user_id));
        }
        $openCase = $caseQuery->latest('identified_at')->first();

        $operation_cat = DB::table('operation_categories')
            ->orderBy('id')
            ->get();

        $material = DB::table('materials')
            ->orderBy('id')
            ->get();






        /*
        |--------------------------------------------------------------------------
        | عملیات
        |--------------------------------------------------------------------------
        */

        $operationQuery = Operation::with('operationMaterial')
            ->with('operationCategory')
            ->with('user')
            ->with('images')
            ->where('riser_id', $id);
        if ($currentUser->hasRole('boss')) {
            $operationQuery->whereIn('group_user_id', $currentUser->managedGroupIds());
        }
        $operations = $operationQuery
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'maintenance_case_id' => $item->maintenance_case_id,
                    'opencat' => $item->operationCategory,
                    'date' => $item->operation_date,
                    'user' => $item->user,
                    'status' => $item->status,
                    'description' => $item->description,
                    'materials' => $item->operationMaterial,
                    'photos' => $item->images->map(function ($photo) {
                        return [
                            'id' => $photo->id,
                            'url' => asset('storage/' . $photo->path),
                        ];
                    }),
                ];
            });


        /*
        |--------------------------------------------------------------------------
        | ناظرها
        |--------------------------------------------------------------------------
        */

        // $supervisors = User::role('supervisor')->where('zone_id', $zone->id)->get();

        /*
|--------------------------------------------------------------------------
| تاریخچه گردش کار
|--------------------------------------------------------------------------
*/

        $workflowQuery = WorkflowTransfer::with([
            'fromUser:id,name',
            'toUser:id,name',
            'operation:id,riser_id'
        ])
            ->where('riser_id', $id);
        if ($currentUser->hasRole('boss')) {
            $workflowQuery->whereIn('operation_id', $operationQuery->select('operations.id'));
        }
        $workflow = $workflowQuery->orderBy('send_date', 'desc')->get()
            ->map(function ($item) {

                return [
                    'id' => $item->id,

                    'type' => $item->type,
                    'title' => $item->title,
                    'comment' => $item->comment,

                    'from_user' => $item->fromUser?->name,
                    'to_user' => $item->toUser?->name,

                    'send_date' => $item->send_date?->format('Y/m/d H:i'),
                    'receive_date' => $item->receive_date?->format('Y/m/d H:i'),

                    'status' => $item->receive_date
                        ? 'دریافت شده'
                        : 'در انتظار دریافت'
                ];
            });



        /*
        |--------------------------------------------------------------------------
        | خروجی
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' => true,

            'id' => $riser->id,

            'code' => $riser->r_giscode ?: $riser->giscode,
            'status' => $riser->status,
            'updated_at' => optional($riser->updated_at)->format('Y/m/d H:i'),
            'zone_name' => $zone?->name,
            'zone_id' => $zone?->id,
            'organization_name' => $zone?->organization_name,
            'open_case' => $openCase ? [
                'id' => $openCase->id,
                'status' => $openCase->status,
                'identified_at' => $openCase->identified_at,
                'operations_count' => $openCase->operations()->count(),
                'operations_create_url' => $openCase->canAcceptOperations()
                    ? route('maintenance-cases.operations.create', $openCase)
                    : null,
                'operations_edit_url' => $currentUser->hasRole('supervisor')
                    && (int) $openCase->assigned_group_id === (int) $currentUser->group_user_id
                    && $openCase->canAcceptOperations()
                    && $openCase->operations()->exists()
                    ? route('maintenance-cases.operations.edit', $openCase)
                    : null,
                'can_edit_operations' => $currentUser->hasRole('supervisor')
                    && (int) $openCase->assigned_group_id === (int) $currentUser->group_user_id
                    && $openCase->canAcceptOperations()
                    && $openCase->operations()->exists(),
                'can_edit_photos' => $currentUser->isSuperAdmin()
                    || ($currentUser->hasRole('boss') && $currentUser->can('operation.edit') && $currentUser->managesGroup((int) $openCase->assigned_group_id))
                    || ($currentUser->hasRole('supervisor') && (int) $currentUser->group_user_id === (int) $openCase->assigned_group_id && $openCase->canAcceptOperations()),
                'photos' => $openCase->photos->map(fn ($photo) => [
                    'id' => $photo->id,
                    'operation_id' => $photo->operation_id,
                    'type' => $photo->type,
                    'url' => route('maintenance-cases.photos.show', $photo),
                ])->values(),
            ] : null,
            'workflow' => $workflow,

            'operation_cat' => $operation_cat,
            'material' => $material,

            'coordinate' => json_decode($riser->wkt),


            // 'subscription'=>$riser->subscription,

            // 'customer'=>$riser->customer_name,

            // 'address'=>$riser->address,

            // 'zone'=>$riser->zone_name,

            // 'install_date'=>$riser->install_date,

            // 'updated_at'=>$riser->updated_at,

            // 'status'=>$riser->status,

            // 'lat'=>$riser->lat,

            // 'lng'=>$riser->lng,


            'operations' => $operations,

            // 'supervisors'=>$supervisors

        ]);
    }


    public function table()
    {
        return redirect()->route('riserIndex');
    }

    public function data(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'ابتدا وارد حساب کاربری شوید.');
        }

        $zoneFilter = '';
        $bindings = [];

        if (! $user->hasRole('superadmin')) {
            if ($user->isGlobalMaintenanceViewer()) {
                $zoneFilter = "
                AND EXISTS (
                    SELECT 1 FROM zone z
                    WHERE z.geom IS NOT NULL
                      AND t.geom_3857 IS NOT NULL
                      AND ST_Intersects(t.geom_3857, ST_Transform(z.geom, 3857))
                )
                ";
            } elseif (!$user->zone_id) {
                return response()->json([]);
            } else {
                $zoneFilter = "
            AND ST_Intersects(
                t.geom_3857,
                ST_Transform(
                    (SELECT z.geom FROM zone z WHERE z.id = ?),
                    3857
                )
            )
        ";

            $bindings[] = $user->zone_id;
            }
        }

        $sql = "
        SELECT
            t.id,
            t.giscode AS code,
            t.status AS status,
            tech.name AS technician_name,
            sup.supervisors AS supervisors,
            t.updated_at AS updated_at,
            op_count.operation_count,
            op.operation_date AS last_operation_date

        FROM riser t

        LEFT JOIN LATERAL (
            SELECT
                o.id,
                o.user_id,
                o.operation_date
            FROM operations o
            WHERE o.riser_id = t.id
              AND o.deleted_at IS NULL
            ORDER BY o.operation_date DESC, o.id DESC
            LIMIT 1
        ) op ON true

        LEFT JOIN users tech
            ON tech.id = op.user_id

        LEFT JOIN LATERAL (
            SELECT COUNT(*) AS operation_count
            FROM operations o
            WHERE o.riser_id = t.id
              AND o.deleted_at IS NULL
        ) op_count ON true

        LEFT JOIN LATERAL (
            SELECT json_agg(
                    json_build_object(
                        'name', u.name,
                        'status', os.status
                    )
                    ORDER BY os.order, os.id
                ) AS supervisors
            FROM operation_supervisors os
            JOIN users u ON u.id = os.user_id
            WHERE os.operation_id = op.id
        ) sup ON true

        WHERE 1 = 1
        {$zoneFilter}

        ORDER BY op.operation_date DESC NULLS LAST;
    ";

        $rows = DB::select($sql, $bindings);

        // supervisors از پستگرس به صورت رشته JSON برمی‌گرده، decode می‌کنیم
        foreach ($rows as $row) {
            $row->supervisors = $row->supervisors ? json_decode($row->supervisors) : [];
        }

        return response()->json($rows);
    }

    public function bookmark(Riser $riser)
    {
        $user = Auth::user();
        abort_unless($user && ($user->hasRole('supervisor') || $user->can('flag-riser-operation')), 403);
        $riser = Riser::query()->visibleTo($user)->findOrFail($riser->id);
        $transfer = $this->workflow->flagRiser($riser, $user);
        $this->audit->record('riser.flagged_for_operation', $riser, null, [
            'workflow_transfer_id' => $transfer->id,
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'علمک با موفقیت نشان شد.'
        ]);
    }
}
