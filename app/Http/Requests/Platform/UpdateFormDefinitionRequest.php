<?php

namespace App\Http\Requests\Platform;

class UpdateFormDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('formDefinition'));
    }

    public function rules(): array
    {
        return ['name' => ['sometimes', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'resource_key' => ['nullable', 'string', 'max:100'], 'subject_type' => ['nullable', 'string', 'max:100'], 'is_active' => ['sometimes', 'boolean']];
    }
}
