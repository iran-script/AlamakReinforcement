<?php

namespace App\Http\Requests;

use App\Services\WmsUrlValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateWmsServiceRequest extends FormRequest
{
    public function authorize(): bool { return (bool) $this->user()?->can('wms.manage'); }

    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson()) {
            $isUrl = $validator->errors()->has('url');
            throw new HttpResponseException(response()->json(['success'=>false,'error'=>['code'=>$isUrl?'WMS_ENDPOINT_NOT_ALLOWED':'WMS_VALIDATION_FAILED','title'=>$isUrl?'آدرس سرویس مجاز نیست':'اطلاعات سرویس معتبر نیست','message'=>$validator->errors()->first()]],422));
        }
        parent::failedValidation($validator);
    }

    public function rules(WmsUrlValidator $urls): array
    {
        return ['name'=>['required','string','max:160'], 'url'=>['required','string','max:2000', function ($attribute, $value, $fail) use ($urls): void { if ($error = $urls->error((string) $value)) $fail($error); }], 'version'=>['nullable','in:1.1.1,1.3.0'], 'is_active'=>['sometimes','boolean'], 'default_transparent'=>['sometimes','boolean']];
    }
}
