<?php

namespace App\Services;

use App\Models\GisMapSetting;
use App\Models\User;

class MapProfileService
{
    public function __construct(private MapServiceUrlValidator $urls) {}

    public function activeFor(User $user): ?GisMapSetting
    {
        $query = GisMapSetting::query()->where('is_active', true);
        if ($user->organization_id !== null) {
            $organizationProfile = (clone $query)->where('organization_id', $user->organization_id)->first();
            if ($organizationProfile && $this->isUsable($organizationProfile)) return $organizationProfile;
        }

        $global = (clone $query)->whereNull('organization_id')->first();
        return $global && $this->isUsable($global) ? $global : null;
    }

    /** @return array<string, mixed> */
    public function payload(?GisMapSetting $profile): array
    {
        if (! $profile) {
            $defaultUrl = (string) config('gis.default_base_tile_url');
            if ($this->urls->error($defaultUrl, true)) $defaultUrl = '/tiles/{z}/{x}/{y}.png';
            return [
                'id' => null,
                'name' => 'Default',
                'service_type' => 'xyz_raster',
                'tile_url' => $defaultUrl,
                'style_url' => null,
                'attribution' => config('gis.default_attribution'),
                'min_zoom' => 0,
                'max_zoom' => 22,
            ];
        }

        return [
            'id' => (int) $profile->id,
            'name' => $profile->name,
            'service_type' => $profile->service_type,
            'tile_url' => $profile->base_tile_url,
            'style_url' => $profile->style_url,
            'attribution' => $profile->base_tile_attribution,
            'min_zoom' => (int) $profile->min_zoom,
            'max_zoom' => (int) $profile->max_zoom,
        ];
    }

    /**
     * Return the active profile in the form consumed by server-rendered maps.
     * API/mobile clients intentionally receive relative URLs, while browser
     * views need an absolute URL when the configured profile uses an internal
     * path.
     *
     * @return array<string, mixed>
     */
    public function payloadForView(User $user): array
    {
        $payload = $this->payload($this->activeFor($user));

        foreach (['tile_url', 'style_url'] as $key) {
            if (is_string($payload[$key]) && str_starts_with($payload[$key], '/')) {
                $payload[$key] = url($payload[$key]);
            }
        }

        return $payload;
    }

    private function isUsable(GisMapSetting $profile): bool
    {
        return $this->urls->error((string) $profile->base_tile_url, true) === null
            && ($profile->style_url === null || $this->urls->error((string) $profile->style_url) === null);
    }
}
