<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\FormDefinition;
use App\Services\FormDefinitionService;
use App\Services\FormSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FormResolutionController extends Controller
{
    public function __construct(private FormDefinitionService $definitions, private FormSubmissionService $submissions) {}

    public function __invoke(Request $r): JsonResponse
    {
        Gate::authorize('viewAny', FormDefinition::class);
        $f = $r->validate(['form_key' => ['required', 'string', 'max:80'], 'resource_key' => ['nullable', 'string', 'max:100'], 'subject_type' => ['nullable', 'string', 'max:100'], 'subject_id' => ['nullable', 'integer'], 'workflow_instance_id' => ['nullable', 'integer'], 'workflow_transition_id' => ['nullable', 'integer'], 'data' => ['nullable', 'array', 'max:500']]);
        $v = $this->definitions->resolvePublishedForm($f['form_key'], $r->user()->organization_id, $f['resource_key'] ?? null);
        abort_if(! $v, 404);
        $fields = collect($this->submissions->resolveVisibleFields($v, $r->user(), $f['data'] ?? []))
            ->map(fn ($field) => $field->only([
                'key', 'label', 'description', 'field_type', 'data_type', 'sort_order', 'placeholder',
                'default_value', 'options', 'validation', 'visibility_condition', 'required_condition',
                'is_required',
            ]))->values();

        return response()->json(['data' => ['definition' => ['key' => $v->definition->key, 'name' => $v->definition->name, 'resource_key' => $v->definition->resource_key], 'version' => ['id' => $v->id, 'version' => $v->version, 'schema' => $v->schema, 'ui_schema' => $v->ui_schema], 'fields' => $fields]]);
    }
}
