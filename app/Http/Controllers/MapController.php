<?php

namespace App\Http\Controllers;

use App\Models\GisMapLayer;
use App\Models\GisMapSetting;
use App\Models\Riser;
use App\Models\OperationCategory;
use App\Models\Material;
use App\Models\User;
use App\Models\Zone;
use App\Services\MapProfileService;
use App\Services\MapServiceUrlValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MapController extends Controller
{
    public function index(Request $request, MapProfileService $profiles): View
    {
        $baseMap = $profiles->payloadForView($request->user());

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

        $urlValidator = app(MapServiceUrlValidator::class);
        $layers = $layerDefinitions
            ->map(fn ($layer, $key) => $this->normalizeLayer($layer, $key, $urlValidator))
            ->filter()
            ->values();

        return view('map', [
            'baseMap' => $baseMap,
            'baseTileUrl' => $baseMap['tile_url'],
            'baseTileAttribution' => $baseMap['attribution'],
            'gisLayers' => $layers,
            'isLocal' => config('app.env') === 'local',
            'canViewRisers' => $request->user()->isSuperAdmin() || $request->user()->can('riser.view'),
            'canFieldReport' => (bool) $request->user()->group_user_id
                && (bool) $request->user()->zone_id
                && ! $request->user()->isSuperAdmin()
                && $request->user()->hasRole('supervisor'),
            'operationCategories' => OperationCategory::query()->where('is_active', true)->orderBy('sort')->orderBy('title')->get(['id', 'title']),
            'materials' => Material::query()->where('is_active', true)->orderBy('title')->get(['id', 'title', 'unit']),
            'maintenanceZones' => $request->user()->isSuperAdmin()
                ? Zone::query()->orderBy('name')->get(['id', 'name'])
                : Zone::query()->whereKey($request->user()->zone_id)->get(['id', 'name']),
            'maintenanceSupervisors' => User::query()->whereHas('roles', fn ($roles) => $roles->where('name', 'supervisor')->where('guard_name', 'web'))
                ->when(! $request->user()->isSuperAdmin(), fn ($query) => $query
                    ->where('zone_id', $request->user()->zone_id)
                    ->where('organization_id', $request->user()->organization_id)
                    ->whereIn('group_user_id', $request->user()->hasRole('boss')
                        ? $request->user()->managedGroupIds() : [(int) $request->user()->group_user_id]))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function geojson(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);

        $demoOnly = $request->boolean('demo');
        if ($demoOnly && config('app.env') !== 'local') {
            abort(404);
        }

        $risers = Riser::query()
            ->visibleTo($user)
            ->whereNotNull('riser.geom')
            ->select('riser.id', 'riser.r_giscode', 'riser.giscode', 'riser.status')
            ->selectRaw('ST_AsGeoJSON(ST_Transform(riser.geom, 4326)) AS geometry')
            ->when($demoOnly, function ($query): void {
                $query->where('riser.r_giscode', 'like', 'TEST-RISER-%')
                    ->whereExists(function ($zoneQuery): void {
                        $zoneQuery->selectRaw('1')
                            ->from('zone')
                            ->where('zone.name', 'TEST-ZONE-RISER-DEMO')
                            ->whereRaw('ST_Intersects(zone.geom, riser.geom)');
                    })
                    ->selectSub(
                        DB::table('zone')
                            ->select('name')
                            ->where('name', 'TEST-ZONE-RISER-DEMO')
                            ->whereRaw('ST_Intersects(zone.geom, riser.geom)')
                            ->limit(1),
                        'zone_name'
                    );
            })
            ->get();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $risers->map(fn (Riser $riser) => [
                'type' => 'Feature',
                'geometry' => json_decode($riser->geometry),
                'properties' => [
                    'id' => $riser->id,
                    'r_giscode' => $riser->r_giscode,
                    'giscode' => $riser->giscode,
                    'code' => $riser->r_giscode ?: $riser->giscode,
                    'status' => $riser->status,
                    'zone_name' => $riser->zone_name ?? null,
                ],
            ])->values(),
        ]);
    }

    private function normalizeLayer(array $layer, string $key, MapServiceUrlValidator $urls): ?array
    {
        $type = $layer['type'] ?? null;
        if (! ($layer['visible'] ?? true) || ! in_array($type, ['vector_tile', 'wms'], true) || blank($layer['endpoint'] ?? null) || ($type === 'wms' && blank($layer['layers'] ?? null))) {
            return null;
        }

        $endpoint = $layer['endpoint'];
        if ($urls->error((string) $endpoint, $type === 'vector_tile') !== null) return null;
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

        if ($request->boolean('demo')) {
            abort_if(config('app.env') !== 'local', 404);

            $extent = Riser::query()
                ->visibleTo($user)
                ->where('riser.r_giscode', 'like', 'TEST-RISER-%')
                ->whereNotNull('riser.geom')
                ->whereExists(function ($zoneQuery): void {
                    $zoneQuery->selectRaw('1')
                        ->from('zone')
                        ->where('zone.name', 'TEST-ZONE-RISER-DEMO')
                        ->whereRaw('ST_Intersects(zone.geom, riser.geom)');
                })
                ->selectRaw('ST_XMin(ST_Extent(riser.geom)) AS min_longitude,
                    ST_YMin(ST_Extent(riser.geom)) AS min_latitude,
                    ST_XMax(ST_Extent(riser.geom)) AS max_longitude,
                    ST_YMax(ST_Extent(riser.geom)) AS max_latitude')
                ->first();

            if (! $extent || $extent->min_longitude === null) {
                return response()->json(['is_demo' => true, 'extent' => null]);
            }

            return response()->json([
                'is_demo' => true,
                'extent' => [
                    (float) $extent->min_longitude,
                    (float) $extent->min_latitude,
                    (float) $extent->max_longitude,
                    (float) $extent->max_latitude,
                ],
            ]);
        }

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
