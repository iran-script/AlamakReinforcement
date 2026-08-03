@extends('layouts.app')

@section('title','ثبت پیمان')

@section('content')

<div class="page-eyebrow">CONTRACTS / جدید</div>

<div class="page-heading">
    <h4>
        <i class="bi bi-file-earmark-plus text-primary"></i>
        ثبت پیمان جدید
    </h4>
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

        <form action="{{ route('contract.store') }}" method="POST">
            @include('contract._form')
        </form>

    </div>

</div>

@endsection