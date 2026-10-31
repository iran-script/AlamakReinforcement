<?php

namespace App\Http\Controllers\Api\Platform;

use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\AssignWorkOrderRequest;
use App\Http\Requests\Platform\AttachWorkOrderAssetRequest;
use App\Http\Requests\Platform\ChangeWorkOrderStatusRequest;
use App\Http\Requests\Platform\CreateWorkOrderRequest;
use App\Http\Requests\Platform\UpdateWorkOrderRequest;
use App\Models\Asset;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderType;
use App\Services\AccessPolicyService;
use App\Services\WorkOrderService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkOrderController extends Controller
{
    public function __construct(
        private WorkOrderService $service,
        private AccessPolicyService $access,
    ) {}

    public function types(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WorkOrder::class);
        $query = WorkOrderType::query()->where('is_active', true);
        if (! $request->user()->hasRole('superadmin')) {
            $query->where(fn ($scope) => $scope->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id));
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WorkOrder::class);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(WorkOrderStatus::values())],
            'priority' => ['nullable', Rule::in(WorkOrderPriority::values())],
            'type' => ['nullable', 'integer', Rule::exists('work_order_types', 'id')->where(function ($query) use ($request) {
                if (! $request->user()->hasRole('superadmin')) {
                    $query->where(fn ($scope) => $scope->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id));
                }
            })],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where(function ($query) use ($request) {
                if (! $request->user()->hasRole('superadmin')) {
                    $query->where('organization_id', $request->user()->organization_id);
                }
            })],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'asset_id' => ['nullable', 'integer', 'exists:assets,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = WorkOrder::query()->with(['type:id,key,name', 'assignee:id,name']);
        if (! $request->user()->hasRole('superadmin')) {
            $query->where('organization_id', $request->user()->organization_id)->whereNotNull('organization_id');
        }
        $query->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value))
            ->when($filters['priority'] ?? null, fn ($q, $value) => $q->where('priority', $value))
            ->when($filters['type'] ?? null, fn ($q, $value) => $q->where('work_order_type_id', $value))
            ->when($filters['assigned_to'] ?? null, fn ($q, $value) => $q->where('assigned_to', $value))
            ->when($filters['date_from'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($q, $value) => $q->whereDate('created_at', '<=', $value))
            ->when($filters['asset_id'] ?? null, fn ($q, $value) => $q->whereHas('assets', fn ($assets) => $assets->where('assets.id', $value)));

        if (isset($filters['asset_id'])) {
            $this->visibleAsset($request, (int) $filters['asset_id']);
        }

        return response()->json($query->latest()->paginate($filters['per_page'] ?? 25));
    }

    public function store(CreateWorkOrderRequest $request): JsonResponse
    {
        return $this->mutate(fn () => $this->service->create($request->user(), $request->validated()), 201);
    }

    public function show(Request $request, WorkOrder $workOrder): JsonResponse
    {
        Gate::authorize('view', $workOrder);
        $visibleAssetIds = $request->user()->hasRole('superadmin')
            ? Asset::query()->select('id')
            : $this->access->visibleAssets($request->user())->select('id');
        $workOrder->load([
            'type:id,key,name', 'requester:id,name', 'creator:id,name', 'assignee:id,name', 'contract',
            'assets' => fn ($query) => $query->whereIn('assets.id', $visibleAssetIds)->select('assets.id', 'assets.code', 'assets.name', 'assets.status'),
        ]);
        $geometry = DB::table('work_orders')->where('id', $workOrder->id)->selectRaw('ST_AsGeoJSON(geom) AS geometry')->value('geometry');

        return response()->json(['data' => array_merge($workOrder->toArray(), ['geometry' => $geometry ? json_decode($geometry, true) : null])]);
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        return $this->mutate(fn () => $this->service->update($workOrder, $request->validated()));
    }

    public function assign(AssignWorkOrderRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $assignee = User::query()->findOrFail($request->integer('assigned_to'));

        return $this->mutate(fn () => $this->service->assign($workOrder, $assignee));
    }

    public function status(ChangeWorkOrderStatusRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $status = WorkOrderStatus::from($request->validated('status'));

        return $this->mutate(fn () => $status === WorkOrderStatus::Completed
            ? $this->service->complete($workOrder)
            : $this->service->changeStatus($workOrder, $status));
    }

    public function attachAsset(AttachWorkOrderAssetRequest $request, WorkOrder $workOrder): JsonResponse
    {
        $asset = $this->visibleAsset($request, $request->integer('asset_id'));

        return $this->mutate(fn () => $this->service->attachAsset($workOrder, $asset, $request->validated('relationship_type')), 201);
    }

    public function detachAsset(Request $request, WorkOrder $workOrder, Asset $asset): JsonResponse
    {
        Gate::authorize('attachAsset', $workOrder);
        $this->visibleAsset($request, $asset->id);
        $this->service->detachAsset($workOrder, $asset);

        return response()->json(['message' => 'ارتباط دارایی با دستور کار حذف شد.']);
    }

    private function visibleAsset(Request $request, int $assetId): Asset
    {
        if ($request->user()->hasRole('superadmin')) {
            return Asset::query()->findOrFail($assetId);
        }

        return $this->access->visibleAssets($request->user())->findOrFail($assetId);
    }

    private function mutate(callable $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()], $status);
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['work_order' => $exception->getMessage()]);
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23505') {
                throw ValidationException::withMessages(['code' => 'کد دستور کار یا ارتباط دارایی تکراری است.']);
            }
            throw $exception;
        }
    }
}
