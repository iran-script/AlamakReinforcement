<?php

namespace App\Http\Requests\Platform;

class SaveFormVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $m = $this->route('formVersion') ?? $this->route('formDefinition');

        return $this->user()->can($this->route('formVersion') ? 'update' : 'update', $m);
    }

    public function rules(): array
    {
        return ['schema' => ['nullable', 'array', 'max:200'], 'ui_schema' => ['nullable', 'array', 'max:200'], 'configuration' => ['nullable', 'array', 'max:100']];
    }
}
