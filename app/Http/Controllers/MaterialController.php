<?php

namespace App\Http\Controllers;

use App\Models\Material;
use App\Models\MaterialCategory;
use App\Http\Requests\StoreMaterialRequest;
use App\Http\Requests\UpdateMaterialRequest;

class MaterialController extends Controller
{
    public function index()
    {
        $items = Material::with('category')
            ->orderBy('title')
            ->paginate(15);

        return view('material.index',compact('items'));
    }

    public function create()
    {
        $categories = MaterialCategory::orderBy('title')->get();

        return view('material.create',compact('categories'));
    }

    public function store(StoreMaterialRequest $request)
    {
        Material::create($request->validated());

        return redirect()
            ->route('material.index')
            ->with('success','ثبت شد.');
    }

    public function edit(Material $material)
    {
        $categories = MaterialCategory::orderBy('title')->get();

        return view('material.edit',compact(
            'material',
            'categories'
        ));
    }

    public function update(UpdateMaterialRequest $request,Material $material)
    {
        $material->update($request->validated());

        return redirect()
            ->route('material.index')
            ->with('success','ویرایش شد.');
    }

    public function destroy(Material $material)
    {
        $material->delete();

        return back()->with('success','حذف شد.');
    }
}