<?php

namespace App\Http\Requests\Platform;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

abstract class WorkflowRequest extends FormRequest
{
    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('شما مجوز انجام این عملیات گردش کار را ندارید.');
    }

    public function messages(): array
    {
        return ['required' => 'فیلد :attribute الزامی است.', 'exists' => 'مقدار :attribute معتبر یا قابل دسترسی نیست.', 'in' => 'مقدار :attribute معتبر نیست.', 'array' => 'مقدار :attribute باید ساختار JSON معتبر داشته باشد.'];
    }
}
