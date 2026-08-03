<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Material;
use App\Models\OperationCategory;
use App\Services\ReportService;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReportExport;
use App\Models\Contract;
use App\Models\Zone;




class ReportController extends Controller
{

    protected ReportService $reportService;


    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }



    public function index(Request $request)
    {
        $user = $request->user();

        $zones = $user->hasRole('superadmin')
            ? Zone::orderBy('name')->get()
            : Zone::where('id', $user->zone_id)->get();

        return view('report.index', [

            'users' => User::orderBy('name')->get(),

            'materials' => Material::orderBy('title')->get(),

            'operations' => OperationCategory::orderBy('title')->get(),

            'contracts' => Contract::orderBy('contractor_name')->get(),

            'zones' => $zones,

        ]);
    }





    public function data(Request $request)
    {

        $data = $this->reportService
            ->generate($request);


        return response()->json($data);
    }




    public function excel(Request $request)
    {

        return Excel::download(

            new ReportExport($request),

            'Report.xlsx'

        );
    }



    public function pdf(Request $request)
    {

        $data = $this->reportService
            ->generate($request);


        return view(
            'report.pdf',
            compact('data')
        );
    }
}
