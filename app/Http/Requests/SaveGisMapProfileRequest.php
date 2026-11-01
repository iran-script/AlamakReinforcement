<?php

namespace App\Http\Requests;

use App\Services\AccessPolicyService;
use App\Services\MapServiceUrlValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\GisMapSetting;

class SaveGisMapProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && app(AccessPolicyService::class)->can($this->user(), 'gis_settings', 'configure');
    }

    public function rules(MapServiceUrlValidator $urls): array
    {
        $organizationId = $this->user()?->hasRole('superadmin') ? null : $this->user()?->organization_id;
        $profile = $this->route('gisMapSetting');
        $ignoreId = $profile instanceof GisMapSetting ? $profile->id : null;

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('gis_map_settings', 'name')->where(
                fn ($query) => $organizationId === null ? $query->whereNull('organization_id') : $query->where('organization_id', $organizationId)
            )->ignore($ignoreId)],
            'service_type' => ['required', Rule::in(['xyz_raster', 'vector_mvt'])],
            'base_tile_url' => ['required', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail) use ($urls): void {
                if ($error = $urls->error((string) $value, true)) $fail($error);
            }],
            'style_url' => ['nullable', 'string', 'max:1000', function (string $attribute, mixed $value, \Closure $fail) use ($urls): void {
                if ($value !== null && $value !== '' && ($error = $urls->error((string) $value))) $fail($error);
            }],
            'base_tile_attribution' => ['nullable', 'string', 'max:500'],
            'min_zoom' => ['required', 'integer', 'between:0,22'],
            'max_zoom' => ['required', 'integer', 'between:0,22', 'gte:min_zoom'],
        ];
    }
}
