<?php

namespace App\Http\Controllers;

use App\Models\Riser;
use App\Models\Operation;
use App\Models\User;
use App\Models\Zone;
use App\Models\Contract;
use App\Models\Material;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{

    public function index()
    {


        // =====================
        // علمک‌ها
        // =====================

        $totalRisers = Riser::count();



        $doneRisers = Operation::where('status', 'done')
            ->whereNotNull('riser_id')
            ->distinct('riser_id')
            ->count('riser_id');



        $pendingRisers = $totalRisers - $doneRisers;



        $progress = $totalRisers > 0
            ? round(($doneRisers / $totalRisers) * 100)
            : 0;





        // =====================
        // عملیات
        // =====================

        $todayOperations = Operation::whereDate(
            'created_at',
            today()
        )->count();



        $contract=Contract::withCount('operations')
        ->orderByDesc('operations_count')
            ->limit(10)
            ->get();



        // =====================
        // کاربران
        // =====================


        // کاربرانی که عملیات ثبت کرده‌اند

        $activeUsers = Operation::whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');



        // رتبه کاربران بر اساس تعداد عملیات

        $userStats = User::withCount('operations')
            ->orderByDesc('operations_count')
            ->limit(10)
            ->get();


        // =====================
        // اقلام مصرفی
        // =====================

        // تعداد کل اقلام مصرف شده
        $totalMaterialQty = DB::table('operation_materials')
            ->sum('qty');


        // پرمصرف‌ترین اقلام
        $topMaterials = Material::select(
            'materials.id',
            'materials.title'
        )
            ->join(
                'operation_materials',
                'materials.id',
                '=',
                'operation_materials.material_id'
            )
            ->selectRaw('SUM(operation_materials.qty) as total_qty')
            ->groupBy(
                'materials.id',
                'materials.title'
            )
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();




        // =====================
        // زون ها
        // =====================

        $totalZones = Zone::count();







        // =====================
        // آخرین عملیات
        // =====================


        $latestOperations = Operation::with([

            'riser',

            'operationCategory',

            'user'

        ])

            ->latest()

            ->limit(8)

            ->get();







        return view('dashboard', compact(


            'totalRisers',

            'doneRisers',

            'pendingRisers',

            'progress',


            'todayOperations',


            'activeUsers',

            'userStats',
            'contract',

            'totalZones',


            'latestOperations',

            'totalMaterialQty',
            
            'topMaterials'


        ));
    }
}
