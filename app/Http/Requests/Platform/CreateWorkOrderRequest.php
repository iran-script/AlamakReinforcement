<?php

namespace App\Http\Requests\Platform;

use App\Enums\WorkOrderPriority;
use App\Models\WorkOrder;
use Illuminate\Validation\Rule;

class CreateWorkOrderRequest extends WorkOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WorkOrder::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'work_order_type_id' => ['required', 'integer', $this->typeExists()],
            'code' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::in(WorkOrderPriority::values())],
            'requester_id' => ['nullable', 'integer', $this->organizationExists('users')],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'scheduled_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:scheduled_at'],
            'properties' => ['nullable', 'array'],
            'geometry' => ['nullable', 'array'],
            'geometry.type' => ['required_with:geometry', Rule::in(['Point', 'LineString', 'Polygon', 'MultiPoint', 'MultiLineString', 'MultiPolygon'])],
            'geometry.coordinates' => ['required_with:geometry', 'array'],
        ];
    }
}
