<?php

namespace App\Http\Controllers;

use App\Models\Zone;
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
        return view('zones.manage');
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
             VALUES (?, ?, ?, ST_Transform(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), 32639), now(), now())
             RETURNING id',
            [$validated['name'], $request->user()->id, $request->user()->organization_id, $geometryJson]
        )->id;

        Zone::query()->findOrFail($zoneId);
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

        DB::update(
            'UPDATE zone
             SET name = ?, geom = ST_Transform(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), 32639), updated_at = now()
             WHERE id = ?',
            [$validated['name'], $geometryJson, $zone->id]
        );

        return response()->json(['success' => true, 'message' => 'Zone updated successfully.']);
    }

    public function destroy(Zone $zone)
    {
        Gate::authorize('delete', $zone);
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
                'SELECT ST_IsValid(ST_Transform(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326), 32639)) AS is_valid',
                [$geometryJson]
            );
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'geometry' => 'Zone geometry is not valid GeoJSON.',
            ]);
        }

        if (! $result || ! (bool) $result->is_valid) {
            throw ValidationException::withMessages(['geometry' => 'Zone geometry is invalid or self-intersecting.']);
        }
    }
}
