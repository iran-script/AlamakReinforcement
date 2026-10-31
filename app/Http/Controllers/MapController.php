<?php

namespace App\Http\Controllers;

use App\Models\GisMapLayer;
use App\Models\GisMapSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MapController extends Controller
{
    public function index(Request $request): View
    {
        $setting = GisMapSetting::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->where('organization_id', $request->user()->organization_id)->orWhereNull('organization_id'))
            ->orderByRaw('CASE WHEN organization_id IS NULL THEN 1 ELSE 0 END')
            ->first();
        $baseTileUrl = $setting?->base_tile_url ?? config('gis.default_base_tile_url');
        if (str_starts_with($baseTileUrl, '/')) {
            $baseTileUrl = url($baseTileUrl);
        }

        $layerDefinitions = collect(config('gis.layers', []));
        GisMapLayer::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $request->user()->organization_id))
            ->orderByRaw('CASE WHEN organization_id IS NULL THEN 0 ELSE 1 END')
            ->get()
            ->each(function (GisMapLayer $layer) use ($layerDefinitions): void {
                $definition = $layer->toArray();
                $definition['visible'] = true;
                $definition['layers'] = $layer->wms_layers;
                $layerDefinitions->put($layer->key, $definition);
            });

        $layers = $layerDefinitions
            ->map(fn ($layer, $key) => $this->normalizeLayer($layer, $key))
            ->filter()
            ->values();

        return view('map', [
            'baseTileUrl' => $baseTileUrl,
            'baseTileAttribution' => $setting?->base_tile_attribution ?? config('gis.default_attribution'),
            'gisLayers' => $layers,
        ]);
    }

    private function normalizeLayer(array $layer, string $key): ?array
    {
        $type = $layer['type'] ?? null;
        if (! ($layer['visible'] ?? true) || ! in_array($type, ['vector_tile', 'wms'], true) || blank($layer['endpoint'] ?? null) || ($type === 'wms' && blank($layer['layers'] ?? null))) {
            return null;
        }

        $endpoint = $layer['endpoint'];
        if (str_starts_with($endpoint, '/')) {
            $endpoint = url($endpoint);
        }

        return [
            'key' => $key,
            'title' => $layer['title'] ?? $key,
            'type' => $type,
            'endpoint' => $endpoint,
            'min_zoom' => (int) ($layer['min_zoom'] ?? 0),
            'max_zoom' => (int) ($layer['max_zoom'] ?? 22),
            'wms' => $type === 'wms' ? [
                'layers' => (string) $layer['layers'],
                'format' => in_array($layer['format'] ?? null, ['image/png', 'image/jpeg', 'image/webp'], true) ? $layer['format'] : 'image/png',
                'transparent' => (bool) ($layer['transparent'] ?? true),
                'version' => in_array($layer['version'] ?? null, ['1.1.1', '1.3.0'], true) ? $layer['version'] : '1.3.0',
                'styles' => (string) ($layer['styles'] ?? ''),
                'server_type' => in_array($layer['server_type'] ?? null, ['geoserver', 'mapserver', 'qgis', 'carmentaserver'], true) ? $layer['server_type'] : null,
            ] : null,
        ];
    }

    public function search(Request $request)
    {
        $code = $request->code;

        $row = \App\Models\Riser::query()
            ->visibleTo($request->user())
            ->selectRaw(
                'ST_X(geom) as lon,
                ST_Y(geom) as lat'
            )
            ->where('r_giscode', $code)
            ->first();

        return response()->json($row);
    }

    public function extent(Request $request)
    {
        $user = $request->user();

        // مدیر کل محدودیت زون ندارد
        if ($user->hasRole('superadmin')) {
            return response()->json([
                'is_superadmin' => true,
            ]);
        }

        // کاربر عادی بدون زون، اجازه نمایش داده ندارد
        if (! $user->zone_id) {
            return response()->json([
                'is_superadmin' => false,
                'zone' => null,
            ], 403);
        }

        $zone = DB::selectOne('
            SELECT
                id,
                name,
                ST_AsGeoJSON(ST_Transform(geom,4326)) AS geometry
            FROM public.zone
            WHERE id = ?
              AND geom IS NOT NULL
        ', [$user->zone_id]);

        if (! $zone) {
            return response()->json([
                'message' => 'زون کاربر پیدا نشد.',
            ], 404);
        }

        return response()->json([
            'is_superadmin' => false,
            'zone_id' => $zone->id,
            'zone_name' => $zone->name,
            'geometry' => json_decode($zone->geometry),
        ]);
    }
}
