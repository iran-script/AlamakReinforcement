<?php

namespace App\Http\Controllers\Platform;

use App\Enums\WorkflowVersionStatus;
use App\Http\Controllers\Controller;
use App\Models\FormDefinition;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkflowStudioController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', WorkflowDefinition::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'active' => ['nullable', 'in:0,1'], 'scope' => ['nullable', 'in:organization,global'], 'subject_type' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'in:draft,published,archived']]);
        $query = WorkflowDefinition::with(['versions' => fn ($q) => $q->orderByDesc('version')])->withCount('versions');
        if (! $request->user()->hasRole('superadmin')) {
            $query->where(fn ($q) => $q->where('organization_id', $request->user()->organization_id)->orWhereNull('organization_id'));
        }
        $query->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($x) => $x->where('name', 'ilike', "%{$v}%")->orWhere('key', 'ilike', "%{$v}%")))
            ->when(isset($filters['active']), fn ($q) => $q->where('is_active', (bool) $filters['active']))
            ->when(($filters['scope'] ?? null) === 'global', fn ($q) => $q->whereNull('organization_id'))->when(($filters['scope'] ?? null) === 'organization', fn ($q) => $q->whereNotNull('organization_id'))
            ->when($filters['subject_type'] ?? null, fn ($q, $v) => $q->where('subject_type', $v))->when($filters['status'] ?? null, fn ($q, $v) => $q->whereHas('versions', fn ($x) => $x->where('status', $v)));

        return view('platform.workflows.index', ['definitions' => $query->latest('updated_at')->paginate(20)->withQueryString(), 'filters' => $filters]);
    }

    public function create()
    {
        Gate::authorize('create', WorkflowDefinition::class);

        return view('platform.workflows.definition-form');
    }

    public function edit(WorkflowDefinition $workflowDefinition)
    {
        Gate::authorize('update', $workflowDefinition);

        return view('platform.workflows.definition-form', compact('workflowDefinition'));
    }

    public function show(WorkflowDefinition $workflowDefinition)
    {
        Gate::authorize('view', $workflowDefinition);
        $workflowDefinition->load(['versions' => fn ($q) => $q->withCount(['states', 'transitions'])->with('publisher:id,name')->orderByDesc('version')]);

        return view('platform.workflows.show', compact('workflowDefinition'));
    }

    public function designer(Request $request, WorkflowVersion $workflowVersion)
    {
        Gate::authorize('view', $workflowVersion);
        $workflowVersion->load('definition:id,key,name,organization_id,subject_type', 'states', 'transitions');
        $editable = $workflowVersion->status === WorkflowVersionStatus::Draft && $request->user()->can('update', $workflowVersion);
        $formKeys = Gate::allows('viewAny', FormDefinition::class)
            ? FormDefinition::query()->where('is_active', true)->where(fn ($q) => $q->where('organization_id', $request->user()->organization_id)->orWhereNull('organization_id'))->whereHas('versions', fn ($q) => $q->where('status', 'published'))->orderBy('name')->get(['key', 'name', 'organization_id'])
            : collect();

        // A user-specific assignee must always belong to the workflow's owning
        // organization. Global workflow templates intentionally expose roles
        // only; binding one template to a specific user would leak across orgs.
        $organizationId = $workflowVersion->definition->organization_id;
        $assignableUsers = $editable && $organizationId !== null
            ? User::query()->where('organization_id', $organizationId)->orderBy('name')->get(['id', 'name', 'username'])
            : collect();
        $assignableRoles = $editable
            ? Role::query()->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('platform.workflows.designer', compact('workflowVersion', 'formKeys', 'editable', 'assignableUsers', 'assignableRoles', 'organizationId'));
    }

    public function preview(WorkflowVersion $workflowVersion)
    {
        Gate::authorize('view', $workflowVersion);
        $workflowVersion->load('definition:id,key,name,organization_id,subject_type', 'states', 'transitions');

        return view('platform.workflows.preview', compact('workflowVersion'));
    }

    public function instance(WorkflowInstance $workflowInstance)
    {
        Gate::authorize('view', $workflowInstance);
        $workflowInstance->load('version.definition:id,key,name,subject_type', 'currentState', 'starter:id,name', 'history.transition:id,key,name', 'history.fromState:id,key,name', 'history.toState:id,key,name', 'history.performer:id,name', 'tasks.assignee:id,name', 'tasks.assignedRole:id,name');

        return view('platform.workflows.instance', compact('workflowInstance'));
    }
}
