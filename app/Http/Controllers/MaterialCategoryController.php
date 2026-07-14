<?php

namespace App\Http\Controllers;

use App\Models\MaterialCategory;
use App\Http\Requests\StoreMaterialCategoryRequest;
use App\Http\Requests\UpdateMaterialCategoryRequest;

class MaterialCategoryController extends Controller
{

    public function index()
    {

        $items = MaterialCategory::orderBy('sort')
            ->orderBy('title')
            ->paginate(15);

        return view(
            'material-category.index',
            compact('items')
        );

    }

    public function create()
    {
        return view('material-category.create');
    }

    public function store(StoreMaterialCategoryRequest $request)
    {

        MaterialCategory::create(
            $request->validated()
        );

        return redirect()
            ->route('material-category.index')
            ->with('success','ثبت شد.');

    }

    public function show(MaterialCategory $materialCategory)
    {

    }

    public function edit(MaterialCategory $materialCategory)
    {

        return view(
            'material-category.edit',
            compact('materialCategory')
        );

    }

    public function update(
        UpdateMaterialCategoryRequest $request,
        MaterialCategory $materialCategory
    ){

        $materialCategory->update(
            $request->validated()
        );

        return redirect()
            ->route('material-category.index')
            ->with('success','ویرایش شد.');

    }

    public function destroy(MaterialCategory $materialCategory)
    {

        $materialCategory->delete();

        return back()
            ->with('success','حذف شد.');

    }

}