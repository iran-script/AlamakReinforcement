<?php

namespace App\Http\Requests\Platform;

class PublishWorkflowVersionRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('publish', $this->route('workflowVersion')) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
