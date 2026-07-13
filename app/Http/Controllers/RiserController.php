<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Riser;
use Illuminate\Support\Facades\DB;

class RiserController extends Controller
{

    public function index()
    {
        $risers = Riser::paginate(20);

        return view('riser.index', compact('risers'));
    }

    /**
     * نمایش صفحه
     */
    public function show($id)
    {
        return view('riser.show',[
            'id'=>$id
        ]);
    }


    /**
     * اطلاعات کامل علمک
     */
    public function details($id)
    {

        /*
        |--------------------------------------------------------------------------
        | اطلاعات اصلی علمک
        |--------------------------------------------------------------------------
        */

        $riser=DB::table('riser')
            ->where('id',$id)
            ->first();

        if(!$riser){

            return response()->json([
                'success'=>false,
                'message'=>'علمک پیدا نشد.'
            ],404);

        }


        /*
        |--------------------------------------------------------------------------
        | تصاویر
        |--------------------------------------------------------------------------
        */

        // $photos=DB::table('riser_photo')

        //     ->where('riser_id',$id)

        //     ->orderBy('id')

        //     ->get()

        //     ->map(function($item){

        //         return [

        //             'id'=>$item->id,

        //             'url'=>asset('storage/'.$item->path),

        //             'title'=>$item->title

        //         ];

        //     });



        /*
        |--------------------------------------------------------------------------
        | عملیات
        |--------------------------------------------------------------------------
        */

        // $operations=DB::table('riser_operation')

        //     ->where('riser_id',$id)

        //     ->orderByDesc('operation_date')

        //     ->get()

        //     ->map(function($item){

        //         return [

        //             'id'=>$item->id,

        //             'date'=>$item->operation_date,

        //             'operation'=>$item->operation_name,

        //             'contractor'=>$item->contractor,

        //             'status'=>$item->status,

        //             'cost'=>number_format($item->cost),

        //             'description'=>$item->description

        //         ];

        //     });



        /*
        |--------------------------------------------------------------------------
        | ناظرها
        |--------------------------------------------------------------------------
        */

        // $supervisors=DB::table('riser_operation_supervisor')

        //     ->join(
        //         'users',
        //         'users.id',
        //         '=',
        //         'riser_operation_supervisor.user_id'
        //     )

        //     ->join(
        //         'riser_operation',
        //         'riser_operation.id',
        //         '=',
        //         'riser_operation_supervisor.operation_id'
        //     )

        //     ->where('riser_operation.riser_id',$id)

        //     ->select(

        //         'users.name',

        //         'users.position',

        //         'riser_operation.operation_name',

        //         'riser_operation_supervisor.approved_at',

        //         'riser_operation_supervisor.result'

        //     )

        //     ->get()

        //     ->map(function($item){

        //         return [

        //             'operation'=>$item->operation_name,

        //             'name'=>$item->name,

        //             'position'=>$item->position,

        //             'date'=>$item->approved_at,

        //             'result'=>$item->result

        //         ];

        //     });



        /*
        |--------------------------------------------------------------------------
        | خروجی
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success'=>true,

            'id'=>$riser->id,

            'code'=>$riser->r_giscode,

            // 'subscription'=>$riser->subscription,

            // 'customer'=>$riser->customer_name,

            // 'address'=>$riser->address,

            // 'zone'=>$riser->zone_name,

            // 'install_date'=>$riser->install_date,

            // 'updated_at'=>$riser->updated_at,

            // 'status'=>$riser->status,

            // 'lat'=>$riser->lat,

            // 'lng'=>$riser->lng,

            // 'photos'=>$photos,

            // 'operations'=>$operations,

            // 'supervisors'=>$supervisors

        ]);

    }

}

