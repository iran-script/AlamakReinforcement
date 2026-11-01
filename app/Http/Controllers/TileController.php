<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;

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

        if (! $user->isSuperAdmin() && ! $user->hasPermission('map.view')) {
            abort(403);
        }

        $filters = $request->validate([
            'zone_id' => ['nullable', 'integer', 'exists:zone,id'],
            'case_status' => ['nullable', 'in:open,in_progress,submitted,returned,completed'],
            'supervisor_id' => ['nullable', 'integer', 'exists:users,id'],
            'operation_category_id' => ['nullable', 'integer', 'exists:operation_categories,id'],
        ]);
        if (! $user->isSuperAdmin() && isset($filters['zone_id']) && (int) $filters['zone_id'] !== (int) $user->zone_id) {
            abort(403);
        }
        if (! $user->isSuperAdmin() && isset($filters['supervisor_id'])) {
            $allowedSupervisor = (int) $filters['supervisor_id'] === (int) $user->id
                || ($user->can('user.view')
                ? User::query()->whereHas('roles', fn ($roles) => $roles->where('name', 'supervisor')->where('guard_name', 'web'))
                    ->where('zone_id', $user->zone_id)->where('organization_id', $user->organization_id)
                    ->whereIn('group_user_id', $user->hasRole('boss') ? $user->managedGroupIds() : [(int) $user->group_user_id])
                    ->whereKey($filters['supervisor_id'])->exists()
                : false);
            abort_unless($allowedSupervisor, 403);
        }

        $zoom = filter_var($z, FILTER_VALIDATE_INT);
        $tileX = filter_var($x, FILTER_VALIDATE_INT);
        $tileY = filter_var($y, FILTER_VALIDATE_INT);
        if ($zoom === false || $tileX === false || $tileY === false || $zoom < 0 || $zoom > 22) {
            abort(404);
        }
        $tileCount = 2 ** $zoom;
        if ($tileX < 0 || $tileY < 0 || $tileX >= $tileCount || $tileY >= $tileCount) {
            abort(404);
        }

        $cfg = $layers[$layer];
        $table = $cfg['table'];
        $code = $cfg['code'];
        $status = $cfg['status'];
        $geom = $cfg['geom'];
        $caseJoin = '';
        $caseFields = '';
        $caseMvtFields = '';
        $caseFilterSql = '';
        $caseFilterBindings = [];
        $caseGroupScope = '';
        $caseGroupBindings = [];
        $caseFilterGroupScope = '';
        $caseFilterGroupBindings = [];
        $canViewMaintenanceCases = $user->isSuperAdmin() || $user->hasPermission('maintenance.case.view');
        $hasCaseFilters = isset($filters['case_status']) || isset($filters['supervisor_id']) || isset($filters['operation_category_id']);
        abort_if($layer === 'riser' && $hasCaseFilters && ! $canViewMaintenanceCases, 403);
        if ($layer === 'riser') {
            if (! $user->isSuperAdmin()) {
                if ($canViewMaintenanceCases) {
                    $caseVisibleGroupIds = $user->can('user.view') && $user->hasRole('boss')
                        ? $user->managedGroupIds() : array_filter([(int) $user->group_user_id]);
                    if ($caseVisibleGroupIds !== []) {
                        $placeholders = implode(',', array_fill(0, count($caseVisibleGroupIds), '?'));
                        $caseGroupScope = ' AND c.assigned_group_id IN ('.$placeholders.')';
                        $caseGroupBindings = $caseVisibleGroupIds;
                        $caseFilterGroupScope = ' AND filter_case.assigned_group_id IN ('.$placeholders.')';
                        $caseFilterGroupBindings = $caseVisibleGroupIds;
                    } else {
                        $caseGroupScope = ' AND 1 = 0';
                        $caseFilterGroupScope = ' AND 1 = 0';
                    }
                } else {
                    $caseGroupScope = ' AND 1 = 0';
                    $caseFilterGroupScope = ' AND 1 = 0';
                }
            }
            $caseJoin = "LEFT JOIN LATERAL (
                SELECT
                    COUNT(*) FILTER (WHERE c.status IN ('open','in_progress','submitted','returned')) AS active_case_count,
                    (array_agg(c.id ORDER BY c.identified_at DESC, c.id DESC)
                        FILTER (WHERE c.status IN ('open','in_progress','submitted','returned')))[1] AS active_case_id,
                    (array_agg(c.status ORDER BY c.identified_at DESC, c.id DESC)
                        FILTER (WHERE c.status IN ('open','in_progress','submitted','returned')))[1] AS active_case_status,
                    (array_agg(c.id ORDER BY c.identified_at DESC, c.id DESC))[1] AS latest_case_id,
                    (array_agg(c.status ORDER BY c.identified_at DESC, c.id DESC))[1] AS latest_case_status
                FROM maintenance_cases c WHERE c.riser_id = t.id {$caseGroupScope}
            ) cases ON true";
            $caseFields = ", COALESCE(cases.active_case_count, 0)::integer AS active_case_count,
                COALESCE(cases.active_case_id, cases.latest_case_id) AS case_id,
                COALESCE(cases.active_case_status, cases.latest_case_status) AS case_status";
            $caseMvtFields = ', data.active_case_count, data.case_id, data.case_status';
            if (isset($filters['case_status'])) {
                $caseFilterSql .= ' AND EXISTS (SELECT 1 FROM maintenance_cases filter_case WHERE filter_case.riser_id=t.id AND filter_case.status = ?'.$caseFilterGroupScope.')';
                $caseFilterBindings[] = $filters['case_status'];
                array_push($caseFilterBindings, ...$caseFilterGroupBindings);
            }
            if (isset($filters['supervisor_id'])) {
                $caseFilterSql .= ' AND EXISTS (SELECT 1 FROM maintenance_cases filter_case WHERE filter_case.riser_id=t.id AND filter_case.reported_by = ?'.$caseFilterGroupScope.')';
                $caseFilterBindings[] = $filters['supervisor_id'];
                array_push($caseFilterBindings, ...$caseFilterGroupBindings);
            }
            if (isset($filters['operation_category_id'])) {
                $caseFilterSql .= ' AND EXISTS (SELECT 1 FROM maintenance_cases filter_case WHERE filter_case.riser_id=t.id AND filter_case.operation_category_id = ?'.$caseFilterGroupScope.')';
                $caseFilterBindings[] = $filters['operation_category_id'];
                array_push($caseFilterBindings, ...$caseFilterGroupBindings);
            }
        }

        $zoneFilter = '';
        $bindings = [];
        $effectiveZoneId = $filters['zone_id'] ?? $user->zone_id;

        if (! $user->isSuperAdmin() || isset($filters['zone_id'])) {
            if (!$effectiveZoneId) {
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

            $bindings[] = $effectiveZoneId;
        }

        $sql = "
            WITH env AS (
                SELECT ST_TileEnvelope(?, ?, ?) AS env_geom
            ),
            data AS (
                SELECT
                    t.id,
                    t.{$code} AS code,
                    t.{$status} AS status{$caseFields},
                    CASE
                        WHEN ? < 12 THEN ST_Simplify(t.{$geom}, 5)
                        WHEN ? < 15 THEN ST_Simplify(t.{$geom}, 2)
                        ELSE t.{$geom}
                    END AS geom
                FROM {$table} t
                CROSS JOIN env
                {$caseJoin}
                WHERE t.{$geom} && env.env_geom
                {$zoneFilter}
                {$caseFilterSql}
                LIMIT 1500
            ),
            mvtgeom AS (
                SELECT
                    data.id,
                    data.code,
                    data.status{$caseMvtFields},
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
            [$zoom, $tileX, $tileY, $zoom, $zoom],
            $caseGroupBindings,
            $bindings,
            $caseFilterBindings,
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
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
