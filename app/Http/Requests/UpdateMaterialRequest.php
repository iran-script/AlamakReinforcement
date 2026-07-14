<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [

            'material_category_id'=>'required|exists:material_categories,id',

            'title'=>'required|max:255',

            'code'=>'nullable|max:100',

            'unit'=>'required|max:100',

            'default_price'=>'required|numeric|min:0',

            'description'=>'nullable',

            'is_active'=>'required|boolean'

        ];
    }
}