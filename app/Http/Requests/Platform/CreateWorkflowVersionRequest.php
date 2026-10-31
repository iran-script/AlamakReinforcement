<?php

namespace App\Http\Requests\Platform;

class CreateWorkflowVersionRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('workflowDefinition')) ?? false;
    }

    public function rules(): array
    {
        return ['configuration' => ['nullable', 'array']];
    }
}
