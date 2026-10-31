<?php

namespace App\Http\Requests\Platform;

class UploadFormSubmissionFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('upload', $this->route('formSubmission'));
    }

    public function rules(): array
    {
        return ['field_key' => ['required', 'string', 'max:80'], 'file' => ['required', 'file', 'max:20480']];
    }
}
