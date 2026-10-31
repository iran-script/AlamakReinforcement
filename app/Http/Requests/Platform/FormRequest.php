<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest as Base;

abstract class FormRequest extends Base
{
    public function messages(): array
    {
        return ['required' => 'فیلد :attribute الزامی است.', 'array' => 'مقدار :attribute باید JSON معتبر باشد.', 'max' => 'مقدار :attribute بیش از حد مجاز است.', 'in' => 'مقدار :attribute معتبر نیست.'];
    }
}
