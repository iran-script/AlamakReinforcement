<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWmsEndpointRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('wms.manage') || (bool) $this->user()?->can('wms.endpoint-approve'); }

    public function rules(): array
    {
        return ['scheme'=>['required','in:http,https'], 'host'=>['required','string','max:255','regex:/^[A-Za-z0-9.-]+$/'], 'port'=>['required','integer','between:1,65535'], 'path_prefix'=>['required','string','max:1000','regex:/^\/[A-Za-z0-9._~\/-]+$/']];
    }
}
