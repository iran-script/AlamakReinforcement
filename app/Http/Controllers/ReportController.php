<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportController extends Controller
{

    public function index()
    {
        return view('report.index');
    }

    public function data(Request $request)
    {

        $query = DB::table('operation')
            ->join('riser', 'operation.riser_id', '=', 'riser.id')
            ->select(
                'operation.*',
                'riser.code as riser_code',
                DB::raw('ST_AsGeoJSON(riser.geom) as geometry')
            );

        /*
        |--------------------------------------------------------------------------
        | Filters
        |--------------------------------------------------------------------------
        */

        if ($request->filled('from')) {

            $query->whereDate('operation.created_at', '>=', $request->from);

        }

        if ($request->filled('to')) {

            $query->whereDate('operation.created_at', '<=', $request->to);

        }

        if ($request->filled('status')) {

            $query->where('operation.status', $request->status);

        }

        if ($request->filled('priority')) {

            $query->where('operation.priority', $request->priority);

        }

        if ($request->filled('contractor')) {

            $query->where('operation.contractor', $request->contractor);

        }

        if ($request->filled('operation')) {

            $query->where('operation.operation', $request->operation);

        }

        /*
        |--------------------------------------------------------------------------
        | Result
        |--------------------------------------------------------------------------
        */

        $operations = $query
            ->orderByDesc('operation.created_at')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Cards
        |--------------------------------------------------------------------------
        */

        $cards = [

            'total' => $operations->count(),

            'done' => $operations
                ->where('status', 'انجام شده')
                ->count(),

            'progress' => $operations
                ->where('status', 'درحال انجام')
                ->count(),

            'stop' => $operations
                ->where('status', 'اتمام')
                ->count(),

            'cost' => $operations->sum('cost'),

            'valve' => $operations->sum('valve_count'),

            'nut' => $operations->sum('nut_count'),

        ];

        /*
        |--------------------------------------------------------------------------
        | Monthly Chart
        |--------------------------------------------------------------------------
        */

        $month = [];

        foreach ($operations as $item) {

            $key = Carbon::parse($item->created_at)
                ->format('Y-m');

            if (!isset($month[$key])) {

                $month[$key] = 0;

            }

            $month[$key]++;

        }

        /*
        |--------------------------------------------------------------------------
        | Status Chart
        |--------------------------------------------------------------------------
        */

        $statusChart = [

            'labels' => [

                'انجام شده',

                'درحال انجام',

                'اتمام'

            ],

            'data' => [

                $cards['done'],

                $cards['progress'],

                $cards['stop']

            ]

        ];

        /*
        |--------------------------------------------------------------------------
        | Contractor Chart
        |--------------------------------------------------------------------------
        */

        $contractor = [];

        foreach ($operations as $item) {

            if (!$item->contractor)
                continue;

            if (!isset($contractor[$item->contractor])) {

                $contractor[$item->contractor] = 0;

            }

            $contractor[$item->contractor]++;

        }

        /*
        |--------------------------------------------------------------------------
        | Priority Chart
        |--------------------------------------------------------------------------
        */

        $priority = [];

        foreach ($operations as $item) {

            if (!isset($priority[$item->priority])) {

                $priority[$item->priority] = 0;

            }

            $priority[$item->priority]++;

        }

        /*
        |--------------------------------------------------------------------------
        | GeoJSON
        |--------------------------------------------------------------------------
        */

        $features = [];

        foreach ($operations as $item) {

            if (!$item->geometry)
                continue;

            $features[] = [

                "type" => "Feature",

                "geometry" => json_decode($item->geometry),

                "properties" => [

                    "id" => $item->id,

                    "riser" => $item->riser_code,

                    "operation" => $item->operation,

                    "status" => $item->status,

                    "priority" => $item->priority,

                    "contractor" => $item->contractor,

                    "cost" => $item->cost

                ]

            ];

        }

        /*
        |--------------------------------------------------------------------------
        | Return
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'cards' => $cards,

            'table' => $operations,

            'monthChart' => [

                'labels' => array_keys($month),

                'data' => array_values($month)

            ],

            'statusChart' => $statusChart,

            'contractorChart' => [

                'labels' => array_keys($contractor),

                'data' => array_values($contractor)

            ],

            'priorityChart' => [

                'labels' => array_keys($priority),

                'data' => array_values($priority)

            ],

            'geojson' => [

                "type" => "FeatureCollection",

                "features" => $features

            ]

        ]);

    }

}