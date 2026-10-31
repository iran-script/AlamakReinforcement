<?php

namespace App\Http\Requests\Platform;

class UpdateWorkflowVersionRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('workflowVersion')) ?? false;
    }

    public function rules(): array
    {
        return ['configuration' => ['nullable', 'array']];
    }
}
