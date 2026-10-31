<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Riser;
use Illuminate\Http\Request;

class RiserApiController extends Controller
{
    /**
     * لیست رایزرهای قابل مشاهده برای کاربر، به‌صورت GeoJSON — برای نقشه‌ی اپ موبایل.
     * از همون الگوی ST_Transform(geom, 4326) که تو RiserController@details امتحان شده استفاده شده.
     */
    public function index(Request $request)
    {
        $risers = Riser::visibleTo($request->user())
            ->select('id', 'r_giscode', 'status', 'updated_at')
            ->selectRaw('ST_AsGeoJSON(ST_Transform(geom, 4326)) as geom_geojson')
            ->get();

        $features = $risers->map(function ($riser) {
            return [
                'type'       => 'Feature',
                'geometry'   => json_decode($riser->geom_geojson),
                'properties' => [
                    'id'         => $riser->id,
                    'code'       => $riser->r_giscode,
                    'status'     => $riser->status,
                    'updated_at' => $riser->updated_at,
                ],
            ];
        });

        return response()->json([
            'type'     => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
