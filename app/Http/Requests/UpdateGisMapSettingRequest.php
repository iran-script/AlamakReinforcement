<?php

namespace App\Http\Requests;

use App\Services\AccessPolicyService;
use App\Services\MapServiceUrlValidator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGisMapSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(AccessPolicyService::class)->can($this->user(), 'gis_settings', 'configure');
    }

    public function rules(MapServiceUrlValidator $urls): array
    {
        return [
            'base_tile_url' => [
                'required',
                'string',
                'max:1000',
                function (string $attribute, mixed $value, \Closure $fail) use ($urls): void {
                    if ($error = $urls->error((string) $value, true)) $fail($error);
                },
            ],
            'base_tile_attribution' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
