<?php

namespace App\Http\Requests\Platform;

use App\Enums\WorkflowStateCategory;
use Illuminate\Validation\Rule;

class UpdateWorkflowStateRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('workflowState')->version) ?? false;
    }

    public function rules(): array
    {
        return ['key' => ['sometimes', 'required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_\-]*$/'], 'name' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['sometimes', 'nullable', 'string'], 'category' => ['sometimes', Rule::in(WorkflowStateCategory::values())], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'configuration' => ['sometimes', 'nullable', 'array']];
    }
}
