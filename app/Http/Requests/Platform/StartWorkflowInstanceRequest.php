<?php

namespace App\Http\Requests\Platform;

use App\Models\WorkflowInstance;

class StartWorkflowInstanceRequest extends WorkflowRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WorkflowInstance::class) ?? false;
    }

    public function rules(): array
    {
        return ['workflow_version_id' => ['required', 'integer', 'exists:workflow_versions,id'], 'work_order_id' => ['required', 'integer', 'exists:work_orders,id'], 'context' => ['nullable', 'array']];
    }
}
