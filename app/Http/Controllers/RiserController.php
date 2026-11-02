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
        $returnTo = request()->query('return_to');
        if (! is_string($returnTo) || ! str_starts_with($returnTo, url('/'))) {
            $returnTo = route('riserIndex');
        }

        return view('riser.show', [
            'id' => $id,
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
            ->select('id', 'r_giscode','geom', 'updated_at', 'status')
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
            ->whereRaw('ST_Intersects(geom, (SELECT geom FROM riser WHERE id = ?))', [$id])
            ->first(['id', 'name']);

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

            'code' => $riser->r_giscode,
            'zone_name' => $zone?->name,
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
            if (!$user->zone_id) {
                return response()->json([]);
            }

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
            ORDER BY o.operation_date DESC, o.id DESC
            LIMIT 1
        ) op ON true

        LEFT JOIN users tech
            ON tech.id = op.user_id

        LEFT JOIN LATERAL (
            SELECT COUNT(*) AS operation_count
            FROM operations o
            WHERE o.riser_id = t.id
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
