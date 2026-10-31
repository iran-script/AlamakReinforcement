<?php

namespace App\Http\Requests\Platform;

class DeleteWorkflowTransitionRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('workflowTransition')->version) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
