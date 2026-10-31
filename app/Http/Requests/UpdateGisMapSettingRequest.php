<?php

namespace App\Http\Requests;

use App\Services\AccessPolicyService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGisMapSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(AccessPolicyService::class)->can($this->user(), 'gis_settings', 'configure');
    }

    public function rules(): array
    {
        return [
            'base_tile_url' => [
                'required',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $hasPlaceholders = str_contains($value, '{z}')
                        && str_contains($value, '{x}')
                        && str_contains($value, '{y}');
                    $isInternalPath = str_starts_with($value, '/') && ! str_starts_with($value, '//');
                    $scheme = parse_url($value, PHP_URL_SCHEME);
                    $isHttpUrl = filter_var($value, FILTER_VALIDATE_URL) !== false
                        && in_array(strtolower((string) $scheme), ['http', 'https'], true);

                    if (! $hasPlaceholders || (! $isInternalPath && ! $isHttpUrl)) {
                        $fail('آدرس کاشی باید یک نشانی HTTP/HTTPS یا مسیر داخلی معتبر و شامل {z}، {x} و {y} باشد.');
                    }
                },
            ],
            'base_tile_attribution' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
