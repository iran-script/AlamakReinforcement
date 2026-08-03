@extends('layouts.app')

@section('title','ایجاد نقش')

@section('content')

<div class="page-eyebrow">ROLES / جدید</div>
<div class="page-heading">
    <h4><i class="bi bi-shield-plus text-primary"></i> ایجاد نقش</h4>
</div>

<div class="card-box p-3 p-md-4" style="max-width: 560px;">

    <form method="POST" action="{{ route('roles.store') }}">
        @csrf
        @include('roles._form')

        <button class="btn btn-success">
            <i class="bi bi-check-circle"></i>
            ذخیره
        </button>
    </form>

</div>

@endsection
