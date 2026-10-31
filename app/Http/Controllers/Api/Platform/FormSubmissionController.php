<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CreateFormSubmissionRequest;
use App\Http\Requests\Platform\SaveFormSubmissionRequest;
use App\Http\Requests\Platform\UploadFormSubmissionFileRequest;
use App\Models\FormSubmission;
use App\Models\FormSubmissionFile;
use App\Models\FormVersion;
use App\Services\FormSubmissionService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FormSubmissionController extends Controller
{
    public function __construct(private FormSubmissionService $service) {}

    public function index(Request $r): JsonResponse
    {
        Gate::authorize('viewAny', FormSubmission::class);
        $f = $r->validate(['status' => ['nullable', 'in:draft,submitted,superseded,cancelled'], 'subject_type' => ['nullable', 'string', 'max:100'], 'subject_id' => ['nullable', 'integer'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $q = FormSubmission::with('version.definition:id,key,name,organization_id')->withCount('files');
        if (! $r->user()->hasRole('superadmin')) {
            $q->where('organization_id', $r->user()->organization_id);
        }foreach (['status', 'subject_type', 'subject_id'] as $k) {
            $q->when($f[$k] ?? null, fn ($q, $v) => $q->where($k, $v));
        }

        return response()->json($q->latest()->paginate($f['per_page'] ?? 25));
    }

    public function store(CreateFormSubmissionRequest $r): JsonResponse
    {
        $d = $r->validated();
        $v = FormVersion::with('definition')->findOrFail($d['form_version_id']);
        unset($d['form_version_id']);

        return $this->mutate(fn () => $this->service->createDraft($v, $r->user(), $d), 201);
    }

    public function show(FormSubmission $formSubmission): JsonResponse
    {
        Gate::authorize('view', $formSubmission);
        $formSubmission->load('version.definition:id,key,name,organization_id', 'version.fields', 'files:id,form_submission_id,field_key,original_name,mime_type,size,metadata,created_at');
        $visible = collect($this->service->resolveVisibleFields($formSubmission->version, request()->user(), $formSubmission->data ?? []));
        $keys = $visible->pluck('key')->all();
        $payload = $formSubmission->toArray();
        $payload['data'] = collect($formSubmission->data ?? [])->only($keys)->all();
        $payload['files'] = collect($payload['files'])->whereIn('field_key', $keys)->values()->all();
        $payload['version']['fields'] = $visible->map(fn ($field) => $field->only([
            'key', 'label', 'description', 'field_type', 'data_type', 'sort_order', 'placeholder',
            'default_value', 'options', 'validation', 'visibility_condition', 'required_condition', 'is_required',
        ]))->values()->all();

        return response()->json(['data' => $payload]);
    }

    public function save(SaveFormSubmissionRequest $r, FormSubmission $formSubmission): JsonResponse
    {
        return $this->mutate(fn () => $this->service->saveDraft($formSubmission, $r->validated('data'), $r->user()));
    }

    public function submit(SaveFormSubmissionRequest $r, FormSubmission $formSubmission): JsonResponse
    {
        return $this->mutate(fn () => $this->service->submit($formSubmission, $r->validated('data'), $r->user()));
    }

    public function upload(UploadFormSubmissionFileRequest $r, FormSubmission $formSubmission): JsonResponse
    {
        return $this->mutate(fn () => $this->service->uploadFile($formSubmission, $r->validated('field_key'), $r->file('file'), $r->user()), 201);
    }

    public function destroyFile(FormSubmission $formSubmission, FormSubmissionFile $formSubmissionFile): JsonResponse
    {
        abort_unless($formSubmissionFile->form_submission_id === $formSubmission->id, 404);
        Gate::authorize('delete', $formSubmissionFile);
        $this->service->removeDraftFile($formSubmissionFile);

        return response()->json(['data' => null]);
    }

    private function mutate(callable $c, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $c()], $status);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['form' => $e->getMessage()]);
        }
    }
}
