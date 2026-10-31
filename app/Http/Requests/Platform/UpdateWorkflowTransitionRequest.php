<?php

namespace App\Http\Requests\Platform;

class UpdateWorkflowTransitionRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('workflowTransition')->version) ?? false;
    }

    public function rules(): array
    {
        return ['key' => ['sometimes', 'required', 'string', 'max:80'], 'name' => ['sometimes', 'required', 'string', 'max:255'], 'from_state_id' => ['sometimes', 'nullable', 'integer', 'exists:workflow_states,id'], 'to_state_id' => ['sometimes', 'required', 'integer', 'exists:workflow_states,id'], 'sort_order' => ['sometimes', 'integer', 'min:0'], 'condition_definition' => ['sometimes', 'nullable', 'array'], 'assignment_definition' => ['sometimes', 'nullable', 'array'], 'required_permission' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/^[a-z_]+:[a-z_]+$/'], 'required_form_key' => ['sometimes', 'nullable', 'string', 'max:120'], 'configuration' => ['sometimes', 'nullable', 'array']];
    }
}
