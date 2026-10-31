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

    public function index(\Illuminate\Http\Request $request)
    {
        $user = $request->user();
        $visibleOperations = fn ($query) => $query->whereHas('riser', fn ($riser) => $riser->visibleTo($user));
        $operations = Operation::query()->where($visibleOperations);
        $visibleOperationIds = Operation::query()->where($visibleOperations)->select('operations.id');


        // =====================
        // علمک‌ها
        // =====================

        $totalRisers = Riser::query()->visibleTo($user)->count();



        $doneRisers = Operation::query()->where($visibleOperations)->where('status', 'done')
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

        $todayOperations = Operation::query()->where($visibleOperations)->whereDate(
            'created_at',
            today()
        )->count();



        $contract = Contract::query()
            ->whereHas('operations', $visibleOperations)
            ->withCount(['operations' => $visibleOperations])
        ->orderByDesc('operations_count')
            ->limit(10)
            ->get();



        // =====================
        // کاربران
        // =====================


        // کاربرانی که عملیات ثبت کرده‌اند

        $activeUsers = Operation::query()->where($visibleOperations)->whereNotNull('user_id')
            ->distinct('user_id')
            ->count('user_id');



        // رتبه کاربران بر اساس تعداد عملیات

        $userStats = User::withCount(['operations' => $visibleOperations])
            ->orderByDesc('operations_count')
            ->limit(10)
            ->get();


        // =====================
        // اقلام مصرفی
        // =====================

        // تعداد کل اقلام مصرف شده
        $totalMaterialQty = DB::table('operation_materials')
            ->whereIn('operation_id', $visibleOperationIds)
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
            ->whereIn('operation_materials.operation_id', $visibleOperationIds)
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

        $totalZones = $user->hasRole('superadmin')
            ? Zone::count()
            : ($user->zone_id ? Zone::whereKey($user->zone_id)->count() : 0);







        // =====================
        // آخرین عملیات
        // =====================


        $latestOperations = Operation::with([

            'riser',

            'operationCategory',

            'user'

        ])->where($visibleOperations)

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
