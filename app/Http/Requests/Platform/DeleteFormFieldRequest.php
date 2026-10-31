<?php

namespace App\Http\Requests\Platform;

class DeleteFormFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('formField')->version);
    }

    public function rules(): array
    {
        return [];
    }
}
