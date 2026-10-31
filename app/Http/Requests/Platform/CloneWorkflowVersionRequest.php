<?php

namespace App\Http\Requests\Platform;

class CloneWorkflowVersionRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('workflowVersion')->definition) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
