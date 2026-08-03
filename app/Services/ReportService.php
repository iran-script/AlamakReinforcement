<?php

namespace App\Services;

use App\Models\Operation;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportService
{

    public function generate(Request $request)
    {
        $user = $request->user();


        if (!$user->hasRole('superadmin')) {

            $request->merge([
                'zone' => $user->zone_id
            ]);
        }

        $query = Operation::with([

            'riser',
            'user',
            'operationCategory',
            'supervisors',
            'operationMaterial.category',
            'contract',

        ]);


        $this->filters($query, $request);



        $operations = $query
            ->latest('operation_date')
            ->get();



        return [

            'cards' => $this->cards($operations),

            'table' => $this->table($operations),

            'riserMaterials' => $this->riserMaterialSummary($operations),

            'monthChart' => $this->monthChart($operations),

            'statusChart' => $this->statusChart($operations),

            'priorityChart' => $this->priorityChart($operations),

            'contractorChart' => $this->contractorChart($operations),

            'materials' => $this->materials($operations),

            'supervisors' => $this->supervisors($operations),

        ];
    }



    private function filters($query, $request)
    {

        if ($request->filled('from')) {

            $query->whereDate(
                'operation_date',
                '>=',
                $request->from
            );
        }


        if ($request->filled('to')) {

            $query->whereDate(
                'operation_date',
                '<=',
                $request->to
            );
        }



        if ($request->filled('status')) {

            $query->where(
                'status',
                $request->status
            );
        }


        if ($request->filled('priority')) {

            $query->where(
                'priority',
                $request->priority
            );
        }

        if ($request->filled('operation_category')) {

            $query->where(
                'operation_category_id',
                $request->operation_category
            );
        }
        if ($request->filled('contract')) {

            $query->where(
                'contract_id',
                $request->contract
            );
        }
        if ($request->filled('zone')) {

            $query->whereHas('riser', function ($q) use ($request) {

                $q->whereRaw("
            ST_Intersects(
                geom_3857,
                ST_Transform(
                    (SELECT geom FROM zone WHERE id = ?),
                    3857
                )
            )
        ", [
                    $request->zone
                ]);
            });
        }
    }






    private function cards($operations)
    {

        return [

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


            'total_cost' => $operations
                ->sum('total_cost'),



            'material_cost' => $operations
                ->flatMap(function ($op) {

                    return $op->operationMaterial;
                })
                ->sum(function ($m) {

                    return $m->pivot->total;
                })

        ];
    }






    private function table($operations)
    {

        $rows = [];


        foreach ($operations as $operation) {


            $rows[] = [


                'riser' => optional($operation->riser)->giscode,


                'operation' => optional($operation->operationCategory)->title,

                'contract_id' => optional($operation->contract)->contractor_name
                    ? optional($operation->contract)->contractor_name . ' - ' . optional($operation->contract)->contract_number
                    : '-',


                'date' => $operation->operation_date,


                'status' => $operation->status,


                'priority' => $operation->priority,


                'user' => optional($operation->user)->name,


                'cost' => $operation->total_cost,



                'materials' => $operation->operationMaterial
                    ->map(function ($m) {

                        return [

                            'title' => $m->title,

                            'unit' => $m->unit,

                            'qty' => $m->pivot->qty,

                            'price' => $m->pivot->price,

                            'total' => $m->pivot->total,

                        ];
                    })

            ];
        }


        return $rows;
    }







    private function riserMaterialSummary($operations)
    {

        $result = [];



        foreach ($operations as $operation) {


            if (!$operation->riser)
                continue;



            $riserId = $operation->riser->id;


            if (!isset($result[$riserId])) {


                $result[$riserId] = [

                    'riser' => $operation->riser->giscode,

                    'materials' => [],

                    'total_cost' => 0

                ];
            }




            foreach ($operation->operationMaterial as $material) {



                $id = $material->id;



                if (!isset($result[$riserId]['materials'][$id])) {


                    $result[$riserId]['materials'][$id] = [

                        'title' => $material->title,

                        'unit' => $material->unit,

                        'qty' => 0,

                        'total' => 0

                    ];
                }



                $result[$riserId]['materials'][$id]['qty']
                    += $material->pivot->qty;



                $result[$riserId]['materials'][$id]['total']
                    += $material->pivot->total;



                $result[$riserId]['total_cost']
                    += $material->pivot->total;
            }
        }



        foreach ($result as &$item) {

            $item['materials']
                =
                array_values($item['materials']);
        }



        return array_values($result);
    }






    private function monthChart($operations)
    {

        $data = [];


        foreach ($operations as $operation) {


            if (!$operation->operation_date)
                continue;


            $month =
                Carbon::parse($operation->operation_date)
                ->format('Y-m');



            $data[$month] =
                ($data[$month] ?? 0) + 1;
        }



        ksort($data);



        return [

            'labels' => array_keys($data),

            'data' => array_values($data)

        ];
    }






    private function statusChart($operations)
    {

        $data = [];


        foreach ($operations as $op) {

            $data[$op->status]
                =
                ($data[$op->status] ?? 0) + 1;
        }


        return [

            'labels' => array_keys($data),

            'data' => array_values($data)

        ];
    }






    private function priorityChart($operations)
    {

        $data = [];


        foreach ($operations as $op) {


            $key = $op->priority ?? 'نامشخص';


            $data[$key]
                =
                ($data[$key] ?? 0) + 1;
        }


        return [

            'labels' => array_keys($data),

            'data' => array_values($data)

        ];
    }






    private function contractorChart($operations)
    {

        $data = [];


        foreach ($operations as $op) {


            if (!$op->user)
                continue;


            $name = $op->user->name;


            $data[$name]
                =
                ($data[$name] ?? 0) + 1;
        }


        return [

            'labels' => array_keys($data),

            'data' => array_values($data)

        ];
    }






    private function materials($operations)
    {

        $data = [];


        foreach ($operations as $op) {


            foreach ($op->operationMaterial as $material) {


                $id = $material->id;


                if (!isset($data[$id])) {


                    $data[$id] = [

                        'title' => $material->title,

                        'unit' => $material->unit,

                        'qty' => 0,

                        'total' => 0

                    ];
                }



                $data[$id]['qty']
                    += $material->pivot->qty;



                $data[$id]['total']
                    += $material->pivot->total;
            }
        }



        return array_values($data);
    }

    private function supervisors($operations)
    {
        $data = [];


        foreach ($operations as $operation) {

            foreach ($operation->supervisors as $supervisor) {


                $id = $supervisor->id;


                if (!isset($data[$id])) {

                    $data[$id] = [
                        'name' => $supervisor->name,
                        'count' => 0
                    ];
                }


                $data[$id]['count']++;
            }
        }


        return array_values($data);
    }
}
