<?php

namespace App\Http\Requests\Platform;

use App\Models\FormSubmission;

class CreateFormSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', FormSubmission::class);
    }

    public function rules(): array
    {
        return ['form_version_id' => ['required', 'integer', 'exists:form_versions,id'], 'organization_id' => ['nullable', 'integer', 'exists:organizations,id'], 'subject_type' => ['nullable', 'string', 'max:100'], 'subject_id' => ['nullable', 'integer'], 'workflow_instance_id' => ['nullable', 'integer', 'exists:workflow_instances,id'], 'workflow_transition_id' => ['nullable', 'integer', 'exists:workflow_transitions,id'], 'context' => ['nullable', 'array', 'max:100']];
    }
}
