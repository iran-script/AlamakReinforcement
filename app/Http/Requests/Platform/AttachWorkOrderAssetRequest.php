<?php

namespace App\Http\Requests\Platform;

use Illuminate\Validation\Rule;

class AttachWorkOrderAssetRequest extends WorkOrderRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attachAsset', $this->route('workOrder')) ?? false;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'relationship_type' => ['required', Rule::in(['primary', 'affected', 'related'])],
        ];
    }
}
