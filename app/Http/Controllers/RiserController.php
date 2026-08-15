<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Riser;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\WorkflowService;
use Illuminate\Support\Facades\Auth;
use App\Models\WorkflowTransfer;


class RiserController extends Controller
{
    // app/Http/Controllers/RiserController.php

    public function __construct(private \App\Services\WorkflowService $workflow) {}

    public function flagOperationRequired(Request $request, Riser $riser)
    {
        abort_unless($request->user()->can('flag-riser-operation'), 403);

        $transfer = $this->workflow->flagRiser($riser, $request->user(), $request->input('comment'));

        return response()->json(['success' => true, 'transfer' => $transfer]);
    }
    public function index()
    {
        $risers = Riser::orderBy('updated_at', 'desc')->paginate(20);

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
            ->select('id', 'r_giscode','geom', 'updated_at', 'status')
            ->selectRaw('ST_Asgeojson(ST_Transform(geom, 4326)) as wkt')
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
        | عملیات
        |--------------------------------------------------------------------------
        */

        $operations = Operation::with('operationMaterial')
            ->with('operationCategory')
            ->with('user')
            ->with('images')
            ->where('riser_id', $id)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'opencat' => $item->operationCategory,
                    'date' => $item->operation_date,
                    'user' => $item->user,
                    'status' => $item->status,
                    'description' => $item->description,
                    'materials' => $item->operationMaterial,
                    'photos' => $item->images->map(function ($photo) {
                        return [
                            'id' => $photo->id,
                            'url' => asset('storage/' . $photo->path),
                        ];
                    }),
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
| تاریخچه گردش کار
|--------------------------------------------------------------------------
*/

        $workflow = WorkflowTransfer::with([
            'fromUser:id,name',
            'toUser:id,name',
            'operation:id,riser_id'
        ])
            ->where('riser_id', $id)
            ->orderBy('send_date', 'desc')
            ->get()
            ->map(function ($item) {

                return [
                    'id' => $item->id,

                    'type' => $item->type,
                    'title' => $item->title,
                    'comment' => $item->comment,

                    'from_user' => $item->fromUser?->name,
                    'to_user' => $item->toUser?->name,

                    'send_date' => $item->send_date?->format('Y/m/d H:i'),
                    'receive_date' => $item->receive_date?->format('Y/m/d H:i'),

                    'status' => $item->receive_date
                        ? 'دریافت شده'
                        : 'در انتظار دریافت'
                ];
            });



        /*
        |--------------------------------------------------------------------------
        | خروجی
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' => true,

            'id' => $riser->id,

            'code' => $riser->r_giscode,
            'workflow' => $workflow,

            'operation_cat' => $operation_cat,
            'material' => $material,

            'coordinate' => json_decode($riser->wkt),


            // 'subscription'=>$riser->subscription,

            // 'customer'=>$riser->customer_name,

            // 'address'=>$riser->address,

            // 'zone'=>$riser->zone_name,

            // 'install_date'=>$riser->install_date,

            // 'updated_at'=>$riser->updated_at,

            // 'status'=>$riser->status,

            // 'lat'=>$riser->lat,

            // 'lng'=>$riser->lng,


            'operations' => $operations,

            // 'supervisors'=>$supervisors

        ]);
    }


    public function table()
    {
        return view('riser.table');
    }

    public function data(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'ابتدا وارد حساب کاربری شوید.');
        }

        $zoneFilter = '';
        $bindings = [];

        if ($user->role !== 'superadmin') {
            if (!$user->zone_id) {
                return response()->json([]);
            }

            $zoneFilter = "
            AND ST_Intersects(
                t.geom_3857,
                ST_Transform(
                    (SELECT z.geom FROM zone z WHERE z.id = ?),
                    3857
                )
            )
        ";

            $bindings[] = $user->zone_id;
        }

        $sql = "
        SELECT
            t.id,
            t.giscode AS code,
            t.status AS status,
            tech.name AS technician_name,
            sup.supervisors AS supervisors,
            t.updated_at AS updated_at,
            op_count.operation_count,
            op.operation_date AS last_operation_date

        FROM riser t

        LEFT JOIN LATERAL (
            SELECT 
                o.id,
                o.user_id,
                o.operation_date
            FROM operations o
            WHERE o.riser_id = t.id
            ORDER BY o.operation_date DESC, o.id DESC
            LIMIT 1
        ) op ON true

        LEFT JOIN users tech 
            ON tech.id = op.user_id

        LEFT JOIN LATERAL (
            SELECT COUNT(*) AS operation_count
            FROM operations o
            WHERE o.riser_id = t.id
        ) op_count ON true

        LEFT JOIN LATERAL (
            SELECT json_agg(
                    json_build_object(
                        'name', u.name,
                        'status', os.status
                    )
                    ORDER BY os.order, os.id
                ) AS supervisors
            FROM operation_supervisors os
            JOIN users u ON u.id = os.user_id
            WHERE os.operation_id = op.id
        ) sup ON true

        WHERE 1 = 1
        {$zoneFilter}

        ORDER BY op.operation_date DESC NULLS LAST;
    ";

        $rows = DB::select($sql, $bindings);

        // supervisors از پستگرس به صورت رشته JSON برمی‌گرده، decode می‌کنیم
        foreach ($rows as $row) {
            $row->supervisors = $row->supervisors ? json_decode($row->supervisors) : [];
        }

        return response()->json($rows);
    }

    public function bookmark(Riser $riser)
    {
        $riser->status = 'flagRiser'; // یا هر مقداری که مدنظرته
        $riser->save();


        $this->workflow->flagRiser($riser, Auth::user());

        return response()->json([
            'status' => 200,
            'message' => 'علمک با موفقیت نشان شد.'
        ]);
    }
}
