<?php

namespace App\Http\Controllers;

use App\Models\OperationCategory;
use App\Http\Requests\StoreOperationCategoryRequest;
use App\Http\Requests\UpdateOperationCategoryRequest;

class OperationCategoryController extends Controller
{

    public function index()
    {
        $items=OperationCategory::orderBy('sort')
            ->orderBy('title')
            ->paginate(15);

        return view(
            'operation-category.index',
            compact('items')
        );
    }

    public function create()
    {
        return view('operation-category.create');
    }

    public function store(StoreOperationCategoryRequest $request)
    {

        OperationCategory::create($request->validated());

        return redirect()
            ->route('operation-category.index')
            ->with('success','با موفقیت ثبت شد.');

    }

    public function show(OperationCategory $operationCategory)
    {

    }

    public function edit(OperationCategory $operationCategory)
    {
        return view(
            'operation-category.edit',
            compact('operationCategory')
        );
    }

    public function update(
        UpdateOperationCategoryRequest $request,
        OperationCategory $operationCategory
    ){

        $operationCategory->update(
            $request->validated()
        );

        return redirect()
            ->route('operation-category.index')
            ->with('success','ویرایش انجام شد.');

    }

    public function destroy(OperationCategory $operationCategory)
    {

        $operationCategory->delete();

        return back()
            ->with('success','حذف شد.');

    }

}