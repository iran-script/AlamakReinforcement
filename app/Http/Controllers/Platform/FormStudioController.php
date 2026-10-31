<?php

namespace App\Http\Controllers\Platform;

use App\Enums\FormVersionStatus;
use App\Http\Controllers\Controller;
use App\Models\FormDefinition;
use App\Models\FormSubmission;
use App\Models\FormSubmissionFile;
use App\Models\FormVersion;
use App\Services\FormSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormStudioController extends Controller
{
    public function __construct(private FormSubmissionService $submissions) {}

    public function index(Request $request)
    {
        Gate::authorize('viewAny', FormDefinition::class);
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'], 'active' => ['nullable', 'in:0,1'],
            'scope' => ['nullable', 'in:organization,global'], 'resource_key' => ['nullable', 'string', 'max:100'],
        ]);
        $query = FormDefinition::query()->with(['versions' => fn ($q) => $q->orderByDesc('version')])->withCount('versions');
        if (! $request->user()->hasRole('superadmin')) {
            $query->where(fn ($q) => $q->where('organization_id', $request->user()->organization_id)->orWhereNull('organization_id'));
        }
        $query->when($filters['search'] ?? null, fn ($q, $value) => $q->where(fn ($x) => $x->where('name', 'ilike', "%{$value}%")->orWhere('key', 'ilike', "%{$value}%")))
            ->when(isset($filters['active']), fn ($q) => $q->where('is_active', (bool) $filters['active']))
            ->when(($filters['scope'] ?? null) === 'global', fn ($q) => $q->whereNull('organization_id'))
            ->when(($filters['scope'] ?? null) === 'organization', fn ($q) => $q->whereNotNull('organization_id'))
            ->when($filters['resource_key'] ?? null, fn ($q, $value) => $q->where('resource_key', $value));

        return view('platform.forms.index', ['definitions' => $query->latest('updated_at')->paginate(20)->withQueryString(), 'filters' => $filters]);
    }

    public function create()
    {
        Gate::authorize('create', FormDefinition::class);

        return view('platform.forms.definition-form');
    }

    public function edit(FormDefinition $formDefinition)
    {
        Gate::authorize('update', $formDefinition);

        return view('platform.forms.definition-form', compact('formDefinition'));
    }

    public function show(FormDefinition $formDefinition)
    {
        Gate::authorize('view', $formDefinition);
        $formDefinition->load(['versions' => fn ($q) => $q->withCount('fields')->with('publisher:id,name')->orderByDesc('version')]);

        return view('platform.forms.show', compact('formDefinition'));
    }

    public function designer(FormVersion $formVersion)
    {
        Gate::authorize('view', $formVersion);
        $formVersion->load('definition:id,key,name,organization_id,resource_key,subject_type', 'fields');

        return view('platform.forms.designer', ['formVersion' => $formVersion, 'editable' => $formVersion->status === FormVersionStatus::Draft && request()->user()->can('update', $formVersion)]);
    }

    public function preview(Request $request, FormVersion $formVersion)
    {
        Gate::authorize('view', $formVersion);
        $formVersion->load('definition:id,key,name,organization_id,resource_key,subject_type', 'fields');
        $fields = $this->submissions->resolveVisibleFields($formVersion, $request->user());

        return view('platform.forms.preview', compact('formVersion', 'fields'));
    }

    public function submission(Request $request, FormSubmission $formSubmission)
    {
        Gate::authorize('view', $formSubmission);
        $formSubmission->load('version.definition:id,key,name,organization_id', 'version.fields', 'submitter:id,name', 'workflowInstance:id,subject_type,subject_id', 'files');
        $fields = collect($this->submissions->resolveVisibleFields($formSubmission->version, $request->user(), $formSubmission->data ?? []));
        $files = $formSubmission->files->whereIn('field_key', $fields->pluck('key'));

        return view('platform.forms.submission', compact('formSubmission', 'fields', 'files'));
    }

    public function download(Request $request, FormSubmission $formSubmission, FormSubmissionFile $formSubmissionFile): StreamedResponse
    {
        abort_unless($formSubmissionFile->form_submission_id === $formSubmission->id, 404);
        Gate::authorize('view', $formSubmissionFile);
        $formSubmission->load('version.fields');
        $visible = collect($this->submissions->resolveVisibleFields($formSubmission->version, $request->user(), $formSubmission->data ?? []))->contains('key', $formSubmissionFile->field_key);
        abort_unless($visible && Storage::disk($formSubmissionFile->disk)->exists($formSubmissionFile->path), 404);

        return Storage::disk($formSubmissionFile->disk)->download($formSubmissionFile->path, $formSubmissionFile->original_name, ['Content-Type' => $formSubmissionFile->mime_type]);
    }
}
