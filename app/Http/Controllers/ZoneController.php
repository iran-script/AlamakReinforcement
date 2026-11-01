<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ZoneController extends Controller
{
    public function manage()
    {
        Gate::authorize('viewAny', Zone::class);
        $user = request()->user();

        return view('zones.manage', [
            'canCreateZones' => Gate::allows('create', Zone::class),
            'canEditZones' => ! $user->hasRole('boss') && ($user->hasRole('superadmin') || $user->can('zone.edit')),
            'canDeleteZones' => ! $user->hasRole('boss') && ($user->hasRole('superadmin') || $user->can('zone.delete')),
            'bossZoneBoundaryUnavailable' => $user->hasRole('boss'),
        ]);
    }

    public function index()
    {
        Gate::authorize('viewAny', Zone::class);

        $zones = Zone::query()
            ->whereNotNull('geom')
            ->whereRaw('ST_IsValid(geom)')
            ->select('id', 'name', DB::raw('ST_AsGeoJSON(ST_Transform(geom,4326)) as geojson'));

        $currentUser = auth()->user();
        if ($currentUser->hasRole('boss')) {
            $zones->where('creator_id', $currentUser->id);
            if ($currentUser->organization_id !== null) {
                $zones->where('organization_id', $currentUser->organization_id);
            }
        } elseif (! $currentUser->hasRole('superadmin') && $currentUser->organization_id !== null) {
            $zones->where('organization_id', $currentUser->organization_id);
        }

        $features = $zones->get()->map(function ($zone) {
            $geometry = json_decode($zone->geojson, true);
            if (empty($geometry['type']) || empty($geometry['coordinates'])) {
                return null;
            }

            return [
                'type' => 'Feature',
                'geometry' => $geometry,
                'properties' => ['id' => $zone->id, 'name' => $zone->name],
            ];
        })->filter()->values();

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    public function store(Request $request)
    {
        Gate::authorize('create', Zone::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'geometry' => ['required', 'array', function (string $attribute, mixed $value, \Closure $fail): void {
                $this->validateGeoJson($value, $fail);
            }],
        ]);

        $geometryJson = json_encode($validated['geometry'], JSON_THROW_ON_ERROR);
        $this->assertValidGeometry($geometryJson);

        $zoneId = DB::selectOne(
            'INSERT INTO zone (name, creator_id, organization_id, geom, created_at, updated_at)
             VALUES (?, ?, ?, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), now(), now())
             RETURNING id',
            [$validated['name'], $request->user()->id, $request->user()->organization_id, $geometryJson]
        )->id;

        $zone = Zone::query()->findOrFail($zoneId);
        app(AuditLogger::class)->record('zone.created', $zone, null, [
            'id' => $zone->id, 'name' => $zone->name, 'organization_id' => $zone->organization_id,
            'creator_id' => $zone->creator_id, 'geometry_sha256' => hash('sha256', $geometryJson),
        ]);
        return response()->json(['success' => true, 'message' => 'Zone created successfully.']);
    }

    public function update(Request $request, Zone $zone)
    {
        Gate::authorize('update', $zone);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'geometry' => ['required', 'array', function (string $attribute, mixed $value, \Closure $fail): void {
                $this->validateGeoJson($value, $fail);
            }],
        ]);

        $geometryJson = json_encode($validated['geometry'], JSON_THROW_ON_ERROR);
        $this->assertValidGeometry($geometryJson);
        $before = [
            'id' => $zone->id, 'name' => $zone->name, 'organization_id' => $zone->organization_id,
            'creator_id' => $zone->creator_id,
            'geometry_sha256' => hash('sha256', (string) DB::scalar('SELECT encode(ST_AsEWKB(geom), ?) FROM zone WHERE id = ?', ['hex', $zone->id])),
        ];

        DB::update(
            'UPDATE zone
             SET name = ?, geom = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), updated_at = now()
             WHERE id = ?',
            [$validated['name'], $geometryJson, $zone->id]
        );

        app(AuditLogger::class)->record('zone.updated', $zone, $before, [
            'id' => $zone->id, 'name' => $validated['name'], 'organization_id' => $zone->organization_id,
            'creator_id' => $zone->creator_id, 'geometry_sha256' => hash('sha256', $geometryJson),
        ]);

        return response()->json(['success' => true, 'message' => 'Zone updated successfully.']);
    }

    public function destroy(Zone $zone)
    {
        Gate::authorize('delete', $zone);
        app(AuditLogger::class)->record('zone.deleted', $zone, [
            'id' => $zone->id, 'name' => $zone->name,
            'organization_id' => $zone->organization_id, 'creator_id' => $zone->creator_id,
        ], null);
        $zone->delete();

        return response()->json(['success' => true, 'message' => 'Zone deleted successfully.']);
    }

    private function validateGeoJson(array $geometry, \Closure $fail): void
    {
        if (! in_array($geometry['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            $fail('Zone geometry must be Polygon or MultiPolygon.');
            return;
        }

        if (! isset($geometry['coordinates']) || ! is_array($geometry['coordinates']) || $geometry['coordinates'] === []) {
            $fail('Zone geometry coordinates are required.');
        }
    }

    private function assertValidGeometry(string $geometryJson): void
    {
        try {
            $result = DB::selectOne(
                "SELECT ST_IsValid(geometry) AS is_valid,
                        ST_SRID(geometry) AS srid,
                        GeometryType(geometry) AS geometry_type,
                        ST_XMin(Box3D(geometry)) AS min_longitude,
                        ST_XMax(Box3D(geometry)) AS max_longitude,
                        ST_YMin(Box3D(geometry)) AS min_latitude,
                        ST_YMax(Box3D(geometry)) AS max_latitude
                 FROM (SELECT ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) AS geometry) AS candidate",
                [$geometryJson]
            );
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'geometry' => 'Zone geometry is not valid GeoJSON.',
            ]);
        }

        if (! $result || ! (bool) $result->is_valid || (int) $result->srid !== 4326
            || ! in_array($result->geometry_type, ['POLYGON', 'MULTIPOLYGON'], true)
            || $result->min_longitude < -180 || $result->max_longitude > 180
            || $result->min_latitude < -90 || $result->max_latitude > 90) {
            throw ValidationException::withMessages(['geometry' => 'Zone geometry is invalid or self-intersecting.']);
        }
    }
}
