<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Material;
use App\Models\OperationCategory;
use App\Services\ReportService;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReportExport;


class ReportController extends Controller
{

    protected ReportService $reportService;


    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }



    public function index()
    {
        return view('report.index', [

            'users' => User::orderBy('name')
                ->get(),


            'materials' => Material::orderBy('title')
                ->get(),


            'operations' => OperationCategory::orderBy('title')
                ->get(),

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