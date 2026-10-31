<?php

namespace App\Http\Requests\Platform;

class ReorderFormFieldsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('formVersion'));
    }

    public function rules(): array
    {
        return ['keys' => ['required', 'array', 'max:200'], 'keys.*' => ['string', 'max:80', 'distinct']];
    }
}
