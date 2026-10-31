<?php

namespace App\Http\Requests\Platform;

class DeleteWorkflowStateRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('workflowState')->version) ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
