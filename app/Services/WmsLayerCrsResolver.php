<?php

namespace App\Services;

use App\Models\WmsLayer;
use App\Models\WmsService;
use Illuminate\Support\Facades\Cache;
use Throwable;

class WmsLayerCrsResolver
{
    public function __construct(private WmsCapabilitiesService $capabilities) {}

    /** @return array<string, list<string>> Layer name to supported CRS values. */
    public function forService(WmsService $service): array
    {
        $stored = $service->layers->mapWithKeys(fn (WmsLayer $layer) => [
            $layer->layer_name => $this->normalize($layer->supported_crs ?: []),
        ])->all();

        if (collect($stored)->every(fn (array $crs) => $this->isWebCrs($crs))) {
            return $stored;
        }

        $key = 'wms:capabilities-crs:'.$service->getKey().':'.hash('sha256', $service->url.'|'.$service->version);
        try {
            $remote = Cache::store('file')->remember($key, now()->addMinutes(5), function () use ($service): array {
                $data = $this->capabilities->fetch($service->url, $service->version);
                return collect($data['layers'])->mapWithKeys(fn (array $layer) => [
                    $layer['layer_name'] => $this->normalize($layer['supported_crs'] ?? []),
                ])->all();
            });
        } catch (Throwable) {
            return $stored;
        }

        foreach ($stored as $name => $crs) {
            $stored[$name] = array_values(array_unique([...$crs, ...($remote[$name] ?? [])]));
        }

        return $stored;
    }

    /** @param array<string, list<string>> $resolved */
    public function forLayer(WmsLayer $layer, array $resolved): array
    {
        return $resolved[$layer->layer_name] ?? $this->normalize($layer->supported_crs ?: []);
    }

    public function preferred(array $crs): ?string
    {
        foreach (['EPSG:3857', 'EPSG:4326'] as $preferred) {
            if (in_array($preferred, $crs, true)) return $preferred;
        }

        return null;
    }

    private function isWebCrs(array $crs): bool
    {
        return $this->preferred($crs) !== null;
    }

    private function normalize(array $crs): array
    {
        return array_values(array_unique(array_map(
            static fn ($value) => strtoupper(trim((string) $value)),
            array_filter($crs, static fn ($value) => is_string($value) && trim($value) !== ''),
        )));
    }
}
