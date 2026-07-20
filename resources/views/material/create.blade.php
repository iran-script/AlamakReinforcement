@extends('layouts.app')

@section('title','ثبت مصالح')

@section('content')

<div class="page-eyebrow">MATERIALS / جدید</div>
<div class="page-heading">
    <h4><i class="bi bi-box-seam text-primary"></i> ثبت مصالح جدید</h4>
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

        <form action="{{ route('material.store') }}" method="POST">
            @include('material._form')
        </form>

    </div>

</div>

@endsection
