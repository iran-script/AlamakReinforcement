<?php

namespace App\Http\Requests\Platform;

class UpdateWorkflowDefinitionRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('workflowDefinition')) ?? false;
    }

    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['sometimes', 'nullable', 'string'], 'is_active' => ['sometimes', 'boolean']];
    }
}
