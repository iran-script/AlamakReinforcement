<?php

namespace App\Http\Requests\Platform;

use App\Models\WorkflowDefinition;
use Illuminate\Validation\Rule;

class CreateWorkflowDefinitionRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WorkflowDefinition::class) ?? false;
    }

    public function rules(): array
    {
        return ['organization_id' => ['nullable', 'integer', 'exists:organizations,id'], 'key' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_\-]*$/'], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'subject_type' => ['required', Rule::in(['work_orders'])], 'is_active' => ['sometimes', 'boolean']];
    }
}
