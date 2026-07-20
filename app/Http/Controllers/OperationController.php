<?php

namespace App\Http\Controllers;

use App\Models\Operation;
use Illuminate\Http\Request;
use App\Models\OperationMaterial;
use App\Models\Riser;

class OperationController extends Controller
{
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
        $operation = Operation::create([
            'riser_id' => $request->riser_id,
            'operation_category_id' => $request->operationcat,
            'status' => $request->status,
            'priority' => $request->priority,
            'description' => $request->description,
            'operation_date' => now(),
            'user_id'=>auth()->id(),
        ]);

        $riser = Riser::findOrFail($request->riser_id);
        $riser->status = 'pending';
        $riser->save();
        

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
