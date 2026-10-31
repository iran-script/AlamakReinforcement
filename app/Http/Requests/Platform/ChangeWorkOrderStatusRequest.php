<?php

namespace App\Http\Requests\Platform;

use App\Enums\WorkOrderStatus;
use Illuminate\Validation\Rule;

class ChangeWorkOrderStatusRequest extends WorkOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('changeStatus', $this->route('workOrder')) ?? false;
    }

    public function rules(): array
    {
        return ['status' => ['required', Rule::in(WorkOrderStatus::values())]];
    }
}
