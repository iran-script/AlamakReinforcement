<?php

namespace App\Http\Requests\Platform;

use App\Enums\FormFieldType;
use Illuminate\Validation\Rule;

class SaveFormFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        $v = $this->route('formVersion') ?? $this->route('formField')?->version;

        return $v && $this->user()->can('update', $v);
    }

    public function rules(): array
    {
        return ['key' => ['sometimes', 'required', 'string', 'regex:/^[a-z][a-z0-9_\-]*$/', 'max:80'], 'label' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:2000'], 'field_type' => ['sometimes', 'required', Rule::in(FormFieldType::values())], 'data_type' => ['nullable', 'string', 'max:40'], 'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000'], 'placeholder' => ['nullable', 'string', 'max:255'], 'default_value' => ['nullable'], 'options' => ['nullable', 'array', 'max:200'], 'validation' => ['nullable', 'array', 'max:20'], 'visibility_condition' => ['nullable', 'array', 'max:20'], 'required_condition' => ['nullable', 'array', 'max:20'], 'access_definition' => ['nullable', 'array', 'max:100'], 'configuration' => ['nullable', 'array', 'max:100'], 'is_required' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean']];
    }
}
