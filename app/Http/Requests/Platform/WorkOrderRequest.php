<?php

namespace App\Http\Requests\Platform;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class WorkOrderRequest extends FormRequest
{
    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('شما مجوز انجام این عملیات روی دستور کار را ندارید.');
    }

    protected function organizationExists(string $table)
    {
        return Rule::exists($table, 'id')->where(function ($query) {
            if (! $this->user()?->hasRole('superadmin')) {
                $query->where('organization_id', $this->user()?->organization_id);
            }
        });
    }

    protected function typeExists()
    {
        return Rule::exists('work_order_types', 'id')->where(function ($query) {
            $query->where('is_active', true);
            if (! $this->user()?->hasRole('superadmin')) {
                $query->where(fn ($scope) => $scope->whereNull('organization_id')->orWhere('organization_id', $this->user()?->organization_id));
            }
        });
    }

    public function messages(): array
    {
        return [
            'required' => 'فیلد :attribute الزامی است.',
            'exists' => 'مقدار انتخاب‌شده برای :attribute معتبر یا قابل دسترسی نیست.',
            'in' => 'مقدار :attribute معتبر نیست.',
            'date' => 'مقدار :attribute باید تاریخ معتبر باشد.',
            'after_or_equal' => 'مقدار :attribute باید بعد از یا برابر :date باشد.',
            'array' => 'مقدار :attribute باید ساختار JSON معتبر داشته باشد.',
        ];
    }
}
