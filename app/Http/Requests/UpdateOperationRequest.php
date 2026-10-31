<?php

namespace App\Http\Requests;

use App\Models\Material;
use App\Models\Operation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $operation = $this->route('operation');

        return $operation instanceof Operation && Gate::allows('update', $operation);
    }

    public function rules(): array
    {
        return [
            'riser_id' => ['sometimes', 'required', 'integer', 'exists:riser,id'],
            'operationcat' => ['sometimes', 'required', 'integer', 'exists:operation_categories,id'],
            'status' => ['sometimes', 'nullable', 'string', 'max:100'],
            'priority' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'materials' => ['sometimes', 'nullable', 'array'],
            'materials.*' => ['numeric', 'gt:0', 'max:99999999.99'],
            'photos_after' => ['sometimes', 'nullable', 'array', 'max:20'],
            'photos_after.*' => ['image', 'max:10240'],
        ];
    }

    protected function passedValidation(): void
    {
        $ids = array_keys($this->input('materials', []));
        if ($ids === []) {
            return;
        }

        if (Material::query()->whereIn('id', $ids)->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['materials' => 'One or more materials are invalid.']);
        }
    }
}
