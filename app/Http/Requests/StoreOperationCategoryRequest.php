<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOperationCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'=>'required|max:255',
            'code'=>'nullable|max:100',
            'description'=>'nullable',
            'sort'=>'nullable|integer',
            'is_active'=>'required|boolean'
        ];
    }
}