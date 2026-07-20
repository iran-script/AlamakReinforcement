<?php

namespace App\Services;

use App\Models\Operation;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{

    public function generate(Request $request)
    {
        $query = Operation::with([
            'riser',
            'user',
            'operationCategory',
            'supervisors',
            'operationMaterial.category'
        ]);


        $this->filters($query,$request);


        $operations = $query
            ->latest('operation_date')
            ->get();


        return [

            'cards'=>$this->cards($operations),

            'table'=>$this->table($operations),

            'monthChart'=>$this->monthChart($operations),

            'statusChart'=>$this->statusChart($operations),

            'priorityChart'=>$this->priorityChart($operations),

            'contractorChart'=>$this->contractorChart($operations),

            'materials'=>$this->materials($operations),

            'supervisors'=>$this->supervisors($operations),

            'geojson'=>$this->geoJson($operations)

        ];

    }



    private function filters($query,$request)
    {

        if($request->filled('from')){

            $query->whereDate(
                'operation_date',
                '>=',
                $request->from
            );

        }


        if($request->filled('to')){

            $query->whereDate(
                'operation_date',
                '<=',
                $request->to
            );

        }


        if($request->filled('status')){

            $query->where(
                'status',
                $request->status
            );

        }


        if($request->filled('priority')){

            $query->where(
                'priority',
                $request->priority
            );

        }


        if($request->filled('contractor')){

            $query->where(
                'user_id',
                $request->contractor
            );

        }


        if($request->filled('operation_category')){

            $query->where(
                'operation_category_id',
                $request->operation_category
            );

        }



        if($request->filled('supervisor')){

            $query->whereHas(
                'supervisors',
                function($q) use($request){

                    $q->where(
                        'users.id',
                        $request->supervisor
                    );

                }
            );

        }



        if($request->filled('material')){

            $query->whereHas(
                'operationMaterial',
                function($q) use($request){

                    $q->where(
                        'material_id',
                        $request->material
                    );

                }
            );

        }


    }



    private function cards($operations)
    {

        $qty=0;
        $cost=0;


        foreach($operations as $operation){

            foreach($operation->operationMaterial as $m){

                $qty += $m->pivot->qty;

                $cost += $m->pivot->total;

            }

        }



        return [

            'total'=>$operations->count(),

            'done'=>$operations
                ->where('status','انجام شده')
                ->count(),


            'progress'=>$operations
                ->where('status','درحال انجام')
                ->count(),


            'stop'=>$operations
                ->where('status','اتمام')
                ->count(),


            'total_cost'=>$operations
                ->sum('total_cost'),


            'material_qty'=>$qty,


            'material_cost'=>$cost

        ];

    }

    private function monthChart($operations)
{
    $data = [];

    foreach ($operations as $operation) {

        if (!$operation->operation_date) {
            continue;
        }

        $month = Carbon::parse($operation->operation_date)
            ->format('Y-m');

        if (!isset($data[$month])) {
            $data[$month] = 0;
        }

        $data[$month]++;
    }


    ksort($data);


    return [
        'labels' => array_keys($data),
        'data'   => array_values($data)
    ];
}



private function statusChart($operations)
{
    $status = [
        'انجام شده' => 0,
        'درحال انجام' => 0,
        'اتمام' => 0,
    ];


    foreach ($operations as $operation) {

        if(isset($status[$operation->status])){

            $status[$operation->status]++;

        }

    }


    return [
        'labels'=>array_keys($status),
        'data'=>array_values($status)
    ];
}



private function priorityChart($operations)
{
    $priority=[];


    foreach($operations as $operation){

        $key=$operation->priority ?: 'نامشخص';


        if(!isset($priority[$key])){

            $priority[$key]=0;

        }


        $priority[$key]++;

    }


    return [

        'labels'=>array_keys($priority),

        'data'=>array_values($priority)

    ];
}



private function contractorChart($operations)
{
    $users=[];


    foreach($operations as $operation){

        if(!$operation->user){
            continue;
        }


        $name=$operation->user->name;


        if(!isset($users[$name])){

            $users[$name]=0;

        }


        $users[$name]++;

    }



    return [

        'labels'=>array_keys($users),

        'data'=>array_values($users)

    ];

}
private function materials($operations)
{
    $materials = [];


    foreach ($operations as $operation) {


        foreach ($operation->operationMaterial as $material) {


            $id = $material->id;


            if (!isset($materials[$id])) {

                $materials[$id] = [

                    'id' => $id,

                    'title' => $material->title,

                    'category' => optional($material->category)->title,

                    'unit' => $material->unit,

                    'qty' => 0,

                    'cost' => 0,

                ];

            }



            $materials[$id]['qty'] += 
                $material->pivot->qty;



            $materials[$id]['cost'] += 
                $material->pivot->total;

        }

    }


    return array_values($materials);
}

}
