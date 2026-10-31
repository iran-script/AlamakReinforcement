<?php

namespace App\Http\Requests;

use App\Models\Material;
use App\Models\Operation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StoreOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Operation::class);
    }

    public function rules(): array
    {
        return [
            'riser_id' => ['required', 'integer', 'exists:riser,id'],
            'operationcat' => ['required', 'integer', 'exists:operation_categories,id'],
            'status' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:10000'],
            'materials' => ['nullable', 'array'],
            'materials.*' => ['numeric', 'gt:0', 'max:99999999.99'],
            'photos_after' => ['nullable', 'array', 'max:20'],
            'photos_after.*' => ['image', 'max:10240'],
        ];
    }

    protected function passedValidation(): void
    {
        $this->validateMaterialKeys();
    }

    private function validateMaterialKeys(): void
    {
        $ids = array_keys($this->input('materials', []));
        if ($ids === []) {
            return;
        }

        $valid = Material::query()->whereIn('id', $ids)->count();
        if ($valid !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['materials' => 'One or more materials are invalid.']);
        }
    }
}
