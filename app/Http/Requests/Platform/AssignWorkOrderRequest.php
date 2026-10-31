<?php

namespace App\Http\Requests\Platform;

class AssignWorkOrderRequest extends WorkOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('assign', $this->route('workOrder')) ?? false;
    }

    public function rules(): array
    {
        return ['assigned_to' => ['required', 'integer', $this->organizationExists('users')]];
    }
}
