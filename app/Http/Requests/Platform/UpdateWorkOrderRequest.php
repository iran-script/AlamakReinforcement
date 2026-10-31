<?php

namespace App\Http\Requests\Platform;

use App\Enums\WorkOrderPriority;
use Illuminate\Validation\Rule;

class UpdateWorkOrderRequest extends WorkOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('workOrder')) ?? false;
    }

    public function rules(): array
    {
        return [
            'work_order_type_id' => ['sometimes', 'required', 'integer', $this->typeExists()],
            'code' => ['sometimes', 'nullable', 'string', 'max:120'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'priority' => ['sometimes', Rule::in(WorkOrderPriority::values())],
            'requester_id' => ['sometimes', 'nullable', 'integer', $this->organizationExists('users')],
            'contract_id' => ['sometimes', 'nullable', 'integer', 'exists:contracts,id'],
            'scheduled_at' => ['sometimes', 'nullable', 'date'],
            'due_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:scheduled_at'],
            'properties' => ['sometimes', 'nullable', 'array'],
            'geometry' => ['sometimes', 'nullable', 'array'],
            'geometry.type' => ['required_with:geometry', Rule::in(['Point', 'LineString', 'Polygon', 'MultiPoint', 'MultiLineString', 'MultiPolygon'])],
            'geometry.coordinates' => ['required_with:geometry', 'array'],
        ];
    }
}
