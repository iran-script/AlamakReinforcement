<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ZoneController extends Controller
{
    public function manage()
    {
        return view('zones.manage');
    }

    public function index()
    {
        $zones = Zone::query()
            ->whereNotNull('geom')
            ->whereRaw('ST_IsValid(geom)')
            ->select(
                'id',
                'name',
                DB::raw('ST_AsGeoJSON(ST_Transform(geom,4326)) as geojson')
            );


        $currentUser = auth()->user();


        if ($currentUser->hasRole('boss')) {

            $zones->where('creator_id', $currentUser->id);
        }


        $zones = $zones->get();



        $features = $zones
            ->map(function ($zone) {

                $geometry = json_decode($zone->geojson, true);


                if (
                    empty($geometry) ||
                    empty($geometry['type']) ||
                    empty($geometry['coordinates'])
                ) {
                    return null;
                }


                return [
                    'type' => 'Feature',

                    'geometry' => $geometry,

                    'properties' => [
                        'id' => $zone->id,
                        'name' => $zone->name,
                    ],
                ];
            })
            ->filter()
            ->values();



        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'geometry' => 'required|array',
        ]);


        $geometryJson = json_encode($request->geometry);

        $zone = Zone::create([
            'name' => $request->name,
            'creator_id' => auth()->id(),
            'geom' => DB::raw(
                "ST_Transform(
                ST_SetSRID(
                    ST_GeomFromGeoJSON('{$geometryJson}'),
                    4326
                ),
                32639
            )"
            ),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'زون با موفقیت ثبت شد.',
        ]);
    }

    public function update(Request $request, Zone $zone)
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'geometry' => 'required|array',
        ]);


        $geometryJson = json_encode($request->geometry);

        $zone->name = $request->name;
        $zone->geom = DB::raw(
            "ST_Transform(
            ST_SetSRID(
                ST_GeomFromGeoJSON('{$geometryJson}'),
                4326
            ),
            32639
        )"
        );
        $zone->save();

        return response()->json([
            'success' => true,
            'message' => 'زون با موفقیت ویرایش شد.',
        ]);
    }


    public function destroy(Zone $zone)
    {
        $zone->delete();

        return response()->json([
            'success' => true,
            'message' => 'زون حذف شد.',
        ]);
    }
}
