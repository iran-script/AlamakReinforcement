<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Riser;
use App\Models\Operation;
use App\Models\User;
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
        return view('riser.show', [
            'id' => $id
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

        $riser = DB::table('riser')
            ->where('id', $id)
            ->first();

        $zone = DB::table('zone')
            ->whereRaw(
                'ST_Contains(geom, ST_Transform(?,32639))',
                [$riser->geom]
            )
            ->first();


        if (!$riser) {

            return response()->json([
                'success' => false,
                'message' => 'علمک پیدا نشد.'
            ], 404);
        }

        $operation_cat = DB::table('operation_categories')
            ->orderBy('id')
            ->get();

        $material = DB::table('materials')
            ->orderBy('id')
            ->get();



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

        $operations = Operation::with('operationMaterial')
        ->with('operationCategory')
        ->with('user')
            ->where('riser_id', $id)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'opencat'=>$item->operationCategory,
                    'date' => $item->operation_date,
                    'user' => $item->user,
                    'status' => $item->status,
                    'description' => $item->description,
                    'materials' => $item->operationMaterial,
                ];
            });



        /*
        |--------------------------------------------------------------------------
        | ناظرها
        |--------------------------------------------------------------------------
        */

        // $supervisors = User::role('supervisor')->where('zone_id', $zone->id)->get();





        /*
        |--------------------------------------------------------------------------
        | خروجی
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' => true,

            'id' => $riser->id,

            'code' => $riser->r_giscode,

            'operation_cat' => $operation_cat,
            'material' => $material,

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

            'operations' => $operations,

            // 'supervisors'=>$supervisors

        ]);
    }
}
