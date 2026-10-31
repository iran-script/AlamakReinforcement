<?php

namespace App\Http\Requests\Platform;

class SaveFormSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('formSubmission'));
    }

    public function rules(): array
    {
        return ['data' => ['required', 'array', 'max:500']];
    }
}
