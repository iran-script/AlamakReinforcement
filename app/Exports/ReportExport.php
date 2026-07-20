<?php

namespace App\Exports;

use App\Models\Operation;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Facades\DB;


class ReportExport implements
    FromCollection,
    WithHeadings,
    WithMapping,
    ShouldAutoSize
{

    protected $request;


    public function __construct(Request $request)
    {
        $this->request = $request;
    }



    public function collection()
    {

        $query = Operation::with([
            'riser',
            'user',
            'operationCategory',
            'operationMaterial',
            'supervisors'
        ]);



        if ($this->request->filled('from')) {

            $query->whereDate(
                'operation_date',
                '>=',
                $this->request->from
            );

        }



        if ($this->request->filled('to')) {

            $query->whereDate(
                'operation_date',
                '<=',
                $this->request->to
            );

        }



        if ($this->request->filled('status')) {

            $query->where(
                'status',
                $this->request->status
            );

        }



        if ($this->request->filled('priority')) {

            $query->where(
                'priority',
                $this->request->priority
            );

        }



        if ($this->request->filled('contractor')) {

            $query->where(
                'user_id',
                $this->request->contractor
            );

        }



        if ($this->request->filled('operation_category')) {

            $query->where(
                'operation_category_id',
                $this->request->operation_category
            );

        }



        if ($this->request->filled('supervisor')) {

            $query->whereHas(
                'supervisors',
                function ($q) {

                    $q->where(
                        'users.id',
                        $this->request->supervisor
                    );

                }
            );

        }



        if ($this->request->filled('material')) {

            $query->whereHas(
                'operationMaterial',
                function ($q) {

                    $q->where(
                        'material_id',
                        $this->request->material
                    );

                }
            );

        }



        return $query
            ->latest('operation_date')
            ->get();

    }




    public function headings(): array
    {
        return [

            'کد علمک',

            'نوع عملیات',

            'تاریخ',

            'وضعیت',

            'اولویت',

            'پیمانکار',

            'هزینه عملیات',

            'تعداد مصالح',

            'هزینه مصالح',

        ];
    }





    public function map($operation): array
    {

        $qty = 0;

        $cost = 0;


        foreach ($operation->operationMaterial as $material) {

            $qty += $material->pivot->qty;

            $cost += $material->pivot->total;

        }



        return [

            optional($operation->riser)->code,

            optional($operation->operationCategory)->title,

            $operation->operation_date,

            $operation->status,

            $operation->priority,

            optional($operation->user)->name,

            $operation->total_cost,

            $qty,

            $cost,

        ];

    }
    private function supervisors($operations)
{
    $supervisors = [];


    foreach ($operations as $operation) {


        foreach ($operation->supervisors as $supervisor) {


            $id = $supervisor->id;


            if (!isset($supervisors[$id])) {

                $supervisors[$id] = [

                    'id' => $id,

                    'name' => $supervisor->name,

                    'operation_count' => 0,

                    'approved' => 0,

                    'rejected' => 0,

                    'pending' => 0,

                ];

            }



            $supervisors[$id]['operation_count']++;



            switch ($supervisor->pivot->status) {


                case 'تایید':

                    $supervisors[$id]['approved']++;

                    break;



                case 'رد':

                    $supervisors[$id]['rejected']++;

                    break;



                default:

                    $supervisors[$id]['pending']++;

                    break;

            }

        }

    }


    return array_values($supervisors);

}
private function geoJson($operations)
{

    $features = [];


    foreach ($operations as $operation) {


        if (
            !$operation->riser ||
            !$operation->riser->geom
        ) {
            continue;
        }



        $geometry = DB::selectOne(
            "
            SELECT ST_AsGeoJSON(geom) as geom
            FROM riser
            WHERE id = ?
            ",
            [
                $operation->riser_id
            ]
        );



        if (!$geometry || !$geometry->geom) {
            continue;
        }



        $features[] = [

            'type' => 'Feature',


            'geometry' => json_decode(
                $geometry->geom
            ),



            'properties' => [

                'id' => $operation->id,

                'riser' =>
                    $operation->riser->code,


                'operation' =>
                    optional(
                        $operation->operationCategory
                    )->title,


                'status' =>
                    $operation->status,


                'priority' =>
                    $operation->priority,


                'contractor' =>
                    optional(
                        $operation->user
                    )->name,


                'cost' =>
                    $operation->total_cost,

            ]

        ];

    }



    return [

        'type' => 'FeatureCollection',

        'features' => $features

    ];

}
private function table($operations)
{
    $table = [];


    foreach ($operations as $operation) {

        $materialQty = 0;
        $materialCost = 0;


        foreach ($operation->operationMaterial as $material) {

            $materialQty += $material->pivot->qty;

            $materialCost += $material->pivot->total;

        }



        $table[] = [

            'id' => $operation->id,


            'riser_code' => optional(
                $operation->riser
            )->code,


            'operation' => optional(
                $operation->operationCategory
            )->title,


            'date' => $operation->operation_date,


            'status' => $operation->status,


            'priority' => $operation->priority,


            'contractor' => optional(
                $operation->user
            )->name,


            'total_cost' => $operation->total_cost,


            'material_qty' => $materialQty,


            'material_cost' => $materialCost,


            'materials' => $operation->operationMaterial,


            'supervisors' => $operation->supervisors

        ];

    }


    return $table;
}

}