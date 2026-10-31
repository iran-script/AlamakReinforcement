<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CompleteWorkflowTaskRequest;
use App\Http\Requests\Platform\ExecuteWorkflowTransitionRequest;
use App\Http\Requests\Platform\StartWorkflowInstanceRequest;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTask;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Models\WorkOrder;
use App\Services\WorkflowRuntimeService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WorkflowRuntimeController extends Controller
{
    public function __construct(private WorkflowRuntimeService $service) {}

    public function start(StartWorkflowInstanceRequest $request): JsonResponse
    {
        $version = WorkflowVersion::query()->with('definition')->findOrFail($request->integer('workflow_version_id'));
        $workOrder = WorkOrder::query()->findOrFail($request->integer('work_order_id'));
        Gate::authorize('view', $workOrder);

        return $this->mutate(fn () => $this->service->startInstance($version, $workOrder, $request->user(), $request->validated('context') ?? []), 201);
    }

    public function show(Request $request, WorkflowInstance $workflowInstance): JsonResponse
    {
        Gate::authorize('view', $workflowInstance);

        return response()->json(['data' => $workflowInstance->load(['version.definition:id,key,name,subject_type', 'currentState:id,key,name,category', 'history.transition:id,key,name', 'tasks'])]);
    }

    public function available(Request $request, WorkflowInstance $workflowInstance): JsonResponse
    {
        Gate::authorize('view', $workflowInstance);

        return $this->mutate(fn () => $this->service->availableTransitions($workflowInstance, $request->user())->values());
    }

    public function transition(ExecuteWorkflowTransitionRequest $request, WorkflowInstance $workflowInstance, WorkflowTransition $workflowTransition): JsonResponse
    {
        return $this->mutate(fn () => $this->service->transition($workflowInstance, $workflowTransition, $request->user(), $request->validated('input') ?? [], $request->validated('comment')));
    }

    public function tasks(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WorkflowTask::class);
        $filters = $request->validate(['status' => ['nullable', 'in:open,completed,cancelled'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $roleIds = $request->user()->roles()->pluck('roles.id');
        $query = WorkflowTask::query()->with(['instance.currentState:id,key,name'])->where(function ($q) use ($request, $roleIds) {
            $q->where('assigned_to', $request->user()->id)->orWhereIn('assigned_role_id', $roleIds);
        });
        if (! $request->user()->hasRole('superadmin')) {
            $query->where('organization_id', $request->user()->organization_id)->whereNotNull('organization_id');
        }
        $query->when($filters['status'] ?? null, fn ($q, $value) => $q->where('status', $value));

        return response()->json($query->latest()->paginate($filters['per_page'] ?? 25));
    }

    public function completeTask(CompleteWorkflowTaskRequest $request, WorkflowTask $workflowTask): JsonResponse
    {
        return $this->mutate(fn () => $this->service->completeTask($workflowTask, $request->user()));
    }

    private function mutate(callable $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()], $status);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['workflow' => $e->getMessage()]);
        }
    }
}
