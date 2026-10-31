<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\CreateFormDefinitionRequest;
use App\Http\Requests\Platform\DeleteFormFieldRequest;
use App\Http\Requests\Platform\FormVersionActionRequest;
use App\Http\Requests\Platform\ReorderFormFieldsRequest;
use App\Http\Requests\Platform\SaveFormFieldRequest;
use App\Http\Requests\Platform\SaveFormVersionRequest;
use App\Http\Requests\Platform\UpdateFormDefinitionRequest;
use App\Models\FormDefinition;
use App\Models\FormField;
use App\Models\FormVersion;
use App\Services\FormDefinitionService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FormDefinitionController extends Controller
{
    public function __construct(private FormDefinitionService $service) {}

    public function index(Request $r): JsonResponse
    {
        Gate::authorize('viewAny', FormDefinition::class);
        $f = $r->validate(['resource_key' => ['nullable', 'string', 'max:100'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);
        $q = FormDefinition::withCount('versions');
        if (! $r->user()->hasRole('superadmin')) {
            $q->where(fn ($x) => $x->where('organization_id', $r->user()->organization_id)->orWhereNull('organization_id'));
        }$q->when($f['resource_key'] ?? null, fn ($q, $v) => $q->where('resource_key', $v));

        return response()->json($q->orderBy('name')->paginate($f['per_page'] ?? 25));
    }

    public function store(CreateFormDefinitionRequest $r): JsonResponse
    {
        return $this->mutate(fn () => $this->service->createDefinition($r->user(), $r->validated()), 201);
    }

    public function show(FormDefinition $formDefinition): JsonResponse
    {
        Gate::authorize('view', $formDefinition);

        return response()->json(['data' => $formDefinition->load(['versions' => fn ($q) => $q->orderByDesc('version')])]);
    }

    public function update(UpdateFormDefinitionRequest $r, FormDefinition $formDefinition): JsonResponse
    {
        return $this->mutate(fn () => $this->service->updateDefinition($formDefinition, $r->validated()));
    }

    public function createVersion(SaveFormVersionRequest $r, FormDefinition $formDefinition): JsonResponse
    {
        return $this->mutate(fn () => $this->service->createDraftVersion($formDefinition, $r->validated()), 201);
    }

    public function showVersion(FormVersion $formVersion): JsonResponse
    {
        Gate::authorize('view', $formVersion);

        return response()->json(['data' => $formVersion->load('definition:id,key,name,organization_id,resource_key', 'fields')]);
    }

    public function updateVersion(SaveFormVersionRequest $r, FormVersion $formVersion): JsonResponse
    {
        return $this->mutate(fn () => $this->service->updateVersion($formVersion, $r->validated()));
    }

    public function addField(SaveFormFieldRequest $r, FormVersion $formVersion): JsonResponse
    {
        return $this->mutate(fn () => $this->service->addField($formVersion, $r->validated()), 201);
    }

    public function updateField(SaveFormFieldRequest $r, FormField $formField): JsonResponse
    {
        return $this->mutate(fn () => $this->service->updateField($formField, $r->validated()));
    }

    public function removeField(DeleteFormFieldRequest $request, FormField $formField): JsonResponse
    {
        return $this->mutate(function () use ($formField) {
            $this->service->removeField($formField);

            return null;
        });
    }

    public function reorder(ReorderFormFieldsRequest $r, FormVersion $formVersion): JsonResponse
    {
        return $this->mutate(fn () => $this->service->reorderFields($formVersion, $r->validated('keys')));
    }

    public function publish(FormVersionActionRequest $r, FormVersion $formVersion): JsonResponse
    {
        return $this->mutate(fn () => $this->service->publishVersion($formVersion, $r->user()));
    }

    public function validateVersion(FormVersionActionRequest $request, FormVersion $formVersion): JsonResponse
    {
        $errors = $this->service->validateVersion($formVersion);

        return response()->json(['data' => ['valid' => $errors === [], 'errors' => $errors]]);
    }

    public function clone(FormVersionActionRequest $r, FormVersion $formVersion): JsonResponse
    {
        return $this->mutate(fn () => $this->service->clonePublishedVersionToDraft($formVersion), 201);
    }

    public function archive(FormVersionActionRequest $r, FormVersion $formVersion): JsonResponse
    {
        return $this->mutate(fn () => $this->service->archiveVersion($formVersion));
    }

    private function mutate(callable $c, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $c()], $status);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['form' => $e->getMessage()]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                throw ValidationException::withMessages(['form' => 'کلید یا شماره نسخه تکراری است.']);
            }throw $e;
        }
    }
}
