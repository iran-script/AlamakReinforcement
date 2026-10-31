<?php

namespace App\Http\Requests\Platform;

class FormVersionActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('publish', $this->route('formVersion'));
    }

    public function rules(): array
    {
        return [];
    }
}
