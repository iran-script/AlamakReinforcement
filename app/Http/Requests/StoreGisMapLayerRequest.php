<?php

namespace App\Http\Requests;

use App\Services\AccessPolicyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGisMapLayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(AccessPolicyService::class)->can($this->user(), 'gis_settings', 'configure');
    }

    public function rules(): array
    {
        $organizationId = $this->user()?->hasRole('superadmin') ? null : $this->user()?->organization_id;

        return [
            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('gis_map_layers', 'key')->where(
                    fn ($query) => $organizationId === null
                        ? $query->whereNull('organization_id')
                        : $query->where('organization_id', $organizationId)
                ),
            ],
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', Rule::in(['vector_tile', 'wms'])],
            'endpoint' => ['required', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail): void {
                $internal = str_starts_with($value, '/') && ! str_starts_with($value, '//');
                $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
                $remote = filter_var($value, FILTER_VALIDATE_URL) !== false && in_array($scheme, ['http', 'https'], true);

                if (! $internal && ! $remote) {
                    $fail('آدرس سرویس باید یک نشانی HTTP/HTTPS یا مسیر داخلی معتبر باشد.');
                }

                if ($this->input('type') === 'vector_tile' && (! str_contains($value, '{z}') || ! str_contains($value, '{x}') || ! str_contains($value, '{y}'))) {
                    $fail('آدرس Vector Tile باید شامل {z}، {x} و {y} باشد.');
                }
            }],
            'wms_layers' => ['nullable', 'required_if:type,wms', 'string', 'max:500', 'regex:/^[A-Za-z0-9_.:,\-]+$/'],
            'format' => ['nullable', Rule::in(['image/png', 'image/jpeg', 'image/webp'])],
            'version' => ['nullable', Rule::in(['1.1.1', '1.3.0'])],
            'styles' => ['nullable', 'string', 'max:500', 'regex:/^[A-Za-z0-9_.:,\-]*$/'],
            'server_type' => ['nullable', Rule::in(['geoserver', 'mapserver', 'qgis', 'carmentaserver'])],
            'transparent' => ['sometimes', 'boolean'],
            'min_zoom' => ['required', 'integer', 'between:0,30'],
            'max_zoom' => ['required', 'integer', 'between:0,30', 'gte:min_zoom'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'کلید باید با حرف انگلیسی شروع شود و فقط شامل حروف کوچک، عدد و زیرخط باشد.',
            'key.unique' => 'این کلید قبلاً برای سازمان ثبت شده است.',
            'wms_layers.required_if' => 'نام لایه منتشرشده در سرویس WMS الزامی است.',
            'max_zoom.gte' => 'بیشترین زوم باید بزرگ‌تر یا مساوی کمترین زوم باشد.',
        ];
    }
}
