<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWmsLayerRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('wms.manage'); }
    public function rules(): array { return ['display_name' => ['nullable','string','max:500'], 'is_enabled' => ['sometimes','boolean'], 'default_visible' => ['sometimes','boolean'], 'opacity' => ['required','numeric','between:0,1'], 'sort_order' => ['required','integer','min:0']]; }
}
