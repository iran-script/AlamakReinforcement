<?php

namespace App\Http\Requests\Platform;

class CreateWorkflowTransitionRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('workflowVersion')) ?? false;
    }

    public function rules(): array
    {
        return ['key' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_\-]*$/'], 'name' => ['required', 'string', 'max:255'], 'from_state_id' => ['nullable', 'integer', 'exists:workflow_states,id'], 'to_state_id' => ['required', 'integer', 'exists:workflow_states,id'], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'condition_definition' => ['nullable', 'array'], 'assignment_definition' => ['nullable', 'array'], 'required_permission' => ['nullable', 'string', 'max:120', 'regex:/^[a-z_]+:[a-z_]+$/'], 'required_form_key' => ['nullable', 'string', 'max:120'], 'configuration' => ['nullable', 'array']];
    }
}
