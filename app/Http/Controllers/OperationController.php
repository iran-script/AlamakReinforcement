<?php

namespace App\Http\Controllers;

use App\Models\Operation;
use Illuminate\Http\Request;
use App\Models\OperationMaterial;
use App\Models\Riser;
use App\Models\Contract;
use App\Services\WorkflowService;
use App\Models\OperationImage;

class OperationController extends Controller
{
    // OperationController.php
    public function __construct(private WorkflowService $workflow) {}



    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $contract_id = auth()->user()->contract_id;
        $operation = Operation::create([
            'riser_id' => $request->riser_id,
            'operation_category_id' => $request->operationcat,
            'status' => $request->status,
            'priority' => $request->priority,
            'description' => $request->description,
            'operation_date' => now(),
            'user_id' => auth()->id(),
            'contract_id' => $contract_id,
        ]);


        // عکس‌های "قبل از تعمیر" که قبلاً (شاید ساعت‌ها قبل) برای این علمک گرفته شدن، به این عملیات وصل میشن
        OperationImage::whereNull('operation_id')
            ->where('riser_id', $operation->riser_id)
            ->where('type', 'before')
            ->update(['operation_id' => $operation->id]);

        // عکس‌های "بعد از تعمیر" همین الان، همراه فرم آپلود میشن
        foreach ($request->file('photos_after', []) as $file) {
            OperationImage::create([
                'operation_id' => $operation->id,
                'type' => 'after',
                'path' => $file->store('operation-photos', 'public'),
                'original_name' => $file->getClientOriginalName(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        $riser = Riser::findOrFail($request->riser_id);
        $riser->status = 'submitOperation';
        $riser->save();

        $technicalInspector = $this->workflow->resolveTechnicalInspector($riser);


        foreach ($request->materials ?? [] as $materialId => $qty) {

            if ($qty <= 0) {
                continue;
            }

            OperationMaterial::create([
                'operation_id' => $operation->id,
                'material_id'  => $materialId,
                'qty'          => $qty,
                'price'        => 0,
                'total'        => 0,
            ]);
        }
        $this->workflow->submitOperationForLeakCheck($operation, $technicalInspector);


        return response()->json([
            'success' => true,
            'message' => 'عملیات ثبت شد.'
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Operation $operation)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Operation $operation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Operation $operation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Operation $operation)
    {
        //
    }
}
