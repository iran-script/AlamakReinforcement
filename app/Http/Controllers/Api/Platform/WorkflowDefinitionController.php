<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\ArchiveWorkflowVersionRequest;
use App\Http\Requests\Platform\CloneWorkflowVersionRequest;
use App\Http\Requests\Platform\CreateWorkflowDefinitionRequest;
use App\Http\Requests\Platform\CreateWorkflowStateRequest;
use App\Http\Requests\Platform\CreateWorkflowTransitionRequest;
use App\Http\Requests\Platform\CreateWorkflowVersionRequest;
use App\Http\Requests\Platform\DeleteWorkflowStateRequest;
use App\Http\Requests\Platform\DeleteWorkflowTransitionRequest;
use App\Http\Requests\Platform\PublishWorkflowVersionRequest;
use App\Http\Requests\Platform\UpdateWorkflowDefinitionRequest;
use App\Http\Requests\Platform\UpdateWorkflowStateRequest;
use App\Http\Requests\Platform\UpdateWorkflowTransitionRequest;
use App\Http\Requests\Platform\UpdateWorkflowVersionRequest;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowState;
use App\Models\WorkflowTransition;
use App\Models\WorkflowVersion;
use App\Services\WorkflowDefinitionService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class WorkflowDefinitionController extends Controller
{
    public function __construct(private WorkflowDefinitionService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', WorkflowDefinition::class);
        $filters = $request->validate(['subject_type' => ['nullable', 'string', 'max:100'], 'active' => ['nullable', 'boolean'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $query = WorkflowDefinition::query()->withCount('versions');
        if (! $request->user()->hasRole('superadmin')) {
            $query->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id));
        }
        $query->when($filters['subject_type'] ?? null, fn ($q, $value) => $q->where('subject_type', $value));
        if (array_key_exists('active', $filters)) {
            $query->where('is_active', $filters['active']);
        }

        return response()->json($query->orderBy('name')->paginate($filters['per_page'] ?? 25));
    }

    public function store(CreateWorkflowDefinitionRequest $request): JsonResponse
    {
        return $this->mutate(fn () => $this->service->createDefinition($request->user(), $request->validated()), 201);
    }

    public function show(Request $request, WorkflowDefinition $workflowDefinition): JsonResponse
    {
        Gate::authorize('view', $workflowDefinition);

        return response()->json(['data' => $workflowDefinition->load(['versions' => fn ($q) => $q->orderByDesc('version')])]);
    }

    public function update(UpdateWorkflowDefinitionRequest $request, WorkflowDefinition $workflowDefinition): JsonResponse
    {
        return $this->mutate(fn () => $this->service->updateDefinition($workflowDefinition, $request->validated()));
    }

    public function createVersion(CreateWorkflowVersionRequest $request, WorkflowDefinition $workflowDefinition): JsonResponse
    {
        return $this->mutate(fn () => $this->service->createDraftVersion($workflowDefinition, $request->validated()), 201);
    }

    public function showVersion(Request $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        Gate::authorize('view', $workflowVersion);

        return response()->json(['data' => $workflowVersion->load(['definition:id,key,name,organization_id', 'states', 'transitions.fromState:id,key,name', 'transitions.toState:id,key,name'])]);
    }

    public function updateVersion(UpdateWorkflowVersionRequest $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        return $this->mutate(fn () => $this->service->updateVersion($workflowVersion, $request->validated()));
    }

    public function addState(CreateWorkflowStateRequest $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        return $this->mutate(fn () => $this->service->addState($workflowVersion, $request->validated()), 201);
    }

    public function updateState(UpdateWorkflowStateRequest $request, WorkflowState $workflowState): JsonResponse
    {
        return $this->mutate(fn () => $this->service->updateState($workflowState, $request->validated()));
    }

    public function addTransition(CreateWorkflowTransitionRequest $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        return $this->mutate(fn () => $this->service->addTransition($workflowVersion, $request->validated()), 201);
    }

    public function updateTransition(UpdateWorkflowTransitionRequest $request, WorkflowTransition $workflowTransition): JsonResponse
    {
        return $this->mutate(fn () => $this->service->updateTransition($workflowTransition, $request->validated()));
    }

    public function removeState(DeleteWorkflowStateRequest $request, WorkflowState $workflowState): JsonResponse
    {
        return $this->mutate(function () use ($workflowState) {
            $this->service->removeState($workflowState);

            return null;
        });
    }

    public function removeTransition(DeleteWorkflowTransitionRequest $request, WorkflowTransition $workflowTransition): JsonResponse
    {
        return $this->mutate(function () use ($workflowTransition) {
            $this->service->removeTransition($workflowTransition);

            return null;
        });
    }

    public function validateVersion(PublishWorkflowVersionRequest $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        $errors = $this->service->validateVersion($workflowVersion);

        return response()->json(['data' => ['valid' => $errors === [], 'errors' => $errors]]);
    }

    public function publish(PublishWorkflowVersionRequest $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        return $this->mutate(fn () => $this->service->publishVersion($workflowVersion, $request->user()));
    }

    public function clone(CloneWorkflowVersionRequest $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        return $this->mutate(fn () => $this->service->clonePublishedVersionToDraft($workflowVersion), 201);
    }

    public function archive(ArchiveWorkflowVersionRequest $request, WorkflowVersion $workflowVersion): JsonResponse
    {
        return $this->mutate(fn () => $this->service->archiveVersion($workflowVersion));
    }

    private function mutate(callable $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()], $status);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['workflow' => $e->getMessage()]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                throw ValidationException::withMessages(['workflow' => 'کلید یا شماره نسخه تکراری است.']);
            } if ($e->getCode() === '23503') {
                throw ValidationException::withMessages(['workflow' => 'وضعیت‌های انتقال باید متعلق به همان نسخه گردش کار باشند.']);
            } throw $e;
        }
    }
}
