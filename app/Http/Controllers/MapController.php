<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class MapController extends Controller
{
    public function index()
    {
        return view('map');
    }   

    public function search(Request $request)
    {
        $code = $request->code;
        
       
        $row = DB::table('riser')
            ->selectRaw(
                'ST_X(geom) as lon,
                ST_Y(geom) as lat'
            )
            ->where('r_giscode',$code)
            ->first();

        return response()->json($row);
    }

    public function extent(Request $request)
    {
        $user = $request->user();

        // مدیر کل محدودیت زون ندارد
        if ($user->role === 'superadmin') {
            return response()->json([
                'is_superadmin' => true,
            ]);
        }

        // کاربر عادی بدون زون، اجازه نمایش داده ندارد
        if (!$user->zone_id) {
            return response()->json([
                'is_superadmin' => false,
                'zone' => null,
            ], 403);
        }

        $zone = DB::selectOne("
            SELECT
                id,
                name,
                ST_AsGeoJSON(ST_Transform(geom,4326)) AS geometry
            FROM public.zone
            WHERE id = ?
              AND geom IS NOT NULL
        ", [$user->zone_id]);

        if (!$zone) {
            return response()->json([
                'message' => 'زون کاربر پیدا نشد.'
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
