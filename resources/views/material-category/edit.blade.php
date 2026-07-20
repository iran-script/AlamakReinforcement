@extends('layouts.app')

@section('title','ویرایش دسته بندی مصالح')

@section('content')

<div class="page-eyebrow">MATERIAL CATEGORIES / ویرایش</div>
<div class="page-heading">
    <h4><i class="bi bi-pencil-square text-primary"></i> ویرایش دسته بندی مصالح</h4>
</div>

<div class="card-box" style="max-width: 760px;">

    <div class="card-header">
        اطلاعات دسته‌بندی
    </div>

    <div class="card-body p-3 p-md-4">

        <form action="{{ route('material-category.update',$materialCategory) }}" method="POST">
            @method('PUT')
            @include('material-category._form')
        </form>

    </div>

</div>

@endsection
