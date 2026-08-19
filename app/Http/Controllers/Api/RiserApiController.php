<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Riser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiserApiController extends Controller
{
    /**
     * لیست رایزرهای قابل مشاهده برای کاربر، به‌صورت GeoJSON
     * برای نمایش روی نقشه‌ی اپ موبایل
     */
    public function index(Request $request)
    {
        $risers = Riser::visibleTo($request->user())
            ->select('*', DB::raw('ST_AsGeoJSON(geom) as geom_geojson'))
            ->get();

        $features = $risers->map(function ($riser) {
            $properties = $riser->toArray();
            unset($properties['geom'], $properties['geom_geojson']);

            return [
                'type'       => 'Feature',
                'geometry'   => json_decode($riser->geom_geojson),
                'properties' => $properties,
            ];
        });

        return response()->json([
            'type'     => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * جزئیات یک رایزر خاص
     */
    public function show(Request $request, Riser $riser)
    {
        // چک دسترسی مکانی: مطمئن شو این رایزر جزو رایزرهای قابل مشاهده‌ی کاربره
        $visible = Riser::visibleTo($request->user())->where('id', $riser->id)->exists();

        if (! $visible) {
            return response()->json(['message' => 'دسترسی نداری.'], 403);
        }

        $riser->loadMissing(['workflowTransfers' => function ($q) {
            $q->latest('send_date');
        }]);

        return response()->json($riser);
    }
}