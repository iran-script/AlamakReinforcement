<?php

namespace App\Http\Requests\Platform;

class ExecuteWorkflowTransitionRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transition', $this->route('workflowInstance')) ?? false;
    }

    public function rules(): array
    {
        return ['input' => ['nullable', 'array'], 'comment' => ['nullable', 'string', 'max:2000']];
    }
}
