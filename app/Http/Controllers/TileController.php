<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TileController extends Controller
{
    public function tile(Request $request, $layer, $z, $x, $y)
    {
        $layers = [
            'riser' => [
                'table' => 'riser',
                'code' => 'giscode',
                'status' => 'status',
                'geom' => 'geom_3857',
            ],
            'valve' => [
                'table' => 'valve',
                'code' => 'valve_code',
                'status' => 'state',
                'geom' => 'geom_3857',
            ],
            'pipe' => [
                'table' => 'pipe',
                'code' => 'pipe_id',
                'status' => 'condition',
                'geom' => 'geom_3857',
            ],
        ];

        if (!isset($layers[$layer])) {
            abort(404);
        }

        $user = $request->user();

        if (!$user) {
            abort(401, 'ابتدا وارد حساب کاربری شوید.');
        }

        $cfg = $layers[$layer];
        $table = $cfg['table'];
        $code = $cfg['code'];
        $status = $cfg['status'];
        $geom = $cfg['geom'];

        $zoneFilter = '';
        $bindings = [];

        if ($user->role !== 'superadmin') {
            if (!$user->zone_id) {
                return response('', 204);
            }

            $zoneFilter = "
                AND ST_Intersects(
                    t.{$geom},
                    ST_Transform(
                        (
                            SELECT z.geom
                            FROM zone z
                            WHERE z.id = ?
                        ),
                        3857
                    )
                )
            ";

            $bindings[] = $user->zone_id;
        }

        $sql = "
            WITH env AS (
                SELECT ST_TileEnvelope(?, ?, ?) AS env_geom
            ),
            data AS (
                SELECT
                    t.id,
                    t.{$code} AS code,
                    t.{$status} AS status,
                    CASE
                        WHEN ? < 12 THEN ST_Simplify(t.{$geom}, 5)
                        WHEN ? < 15 THEN ST_Simplify(t.{$geom}, 2)
                        ELSE t.{$geom}
                    END AS geom
                FROM {$table} t
                CROSS JOIN env
                WHERE t.{$geom} && env.env_geom
                {$zoneFilter}
                LIMIT 1500
            ),
            mvtgeom AS (
                SELECT
                    data.id,
                    data.code,
                    data.status,
                    ST_AsMVTGeom(
                        data.geom,
                        env.env_geom,
                        4096,
                        64,
                        true
                    ) AS geom
                FROM data
                CROSS JOIN env
            )
            SELECT ST_AsMVT(mvtgeom, ?, 4096, 'geom') AS tile
            FROM mvtgeom;
        ";

        $bindings = array_merge(
            [(int) $z, (int) $x, (int) $y, (int) $z, (int) $z],
            $bindings,
            [$layer]
        );

        $result = DB::selectOne($sql, $bindings);

        if (!$result || !$result->tile) {
            return response('', 204);
        }

        $tile = $result->tile;

        if (is_resource($tile)) {
            $tile = stream_get_contents($tile);
        }

        return response((string) $tile, 200, [
            'Content-Type' => 'application/x-protobuf',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}