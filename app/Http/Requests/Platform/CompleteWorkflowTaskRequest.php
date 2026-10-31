<?php

namespace App\Http\Requests\Platform;

class CompleteWorkflowTaskRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('complete', $this->route('workflowTask')) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
