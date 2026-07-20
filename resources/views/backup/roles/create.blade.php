@extends('layouts.app')

@section('title','ایجاد نقش')

@section('content')

<div class="card-box">

<form method="POST"
      action="{{ route('roles.store') }}">

    @csrf

    @include('roles._form')

    <button class="btn btn-success">

        ذخیره

    </button>

</form>

</div>

@endsection