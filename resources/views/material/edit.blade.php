@extends('layouts.app')

@section('title','ویرایش مصالح')

@section('content')

<div class="page-eyebrow">MATERIALS / ویرایش</div>
<div class="page-heading">
    <h4><i class="bi bi-pencil-square text-primary"></i> ویرایش مصالح</h4>
</div>

<div class="card-box" style="max-width: 900px;">

    <div class="card-body p-3 p-md-4">

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('material.update',$material) }}" method="POST">
            @csrf
            @method('PUT')
            @include('material._form')
        </form>

    </div>

</div>

@endsection
