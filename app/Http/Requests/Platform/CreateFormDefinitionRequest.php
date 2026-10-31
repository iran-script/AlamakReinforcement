<?php

namespace App\Http\Requests\Platform;

use App\Models\FormDefinition;

class CreateFormDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FormDefinition::class);
    }

    public function rules(): array
    {
        return ['organization_id' => ['nullable', 'integer', 'exists:organizations,id'], 'key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_\-]*$/', 'max:80'], 'name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:5000'], 'resource_key' => ['nullable', 'string', 'max:100'], 'subject_type' => ['nullable', 'string', 'max:100'], 'is_active' => ['sometimes', 'boolean']];
    }
}
