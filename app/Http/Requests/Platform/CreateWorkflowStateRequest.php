<?php

namespace App\Http\Requests\Platform;

use App\Enums\WorkflowStateCategory;
use Illuminate\Validation\Rule;

class CreateWorkflowStateRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('workflowVersion')) ?? false;
    }

    public function rules(): array
    {
        return ['key' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_\-]*$/'], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'category' => ['required', Rule::in(WorkflowStateCategory::values())], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'configuration' => ['nullable', 'array']];
    }
}
