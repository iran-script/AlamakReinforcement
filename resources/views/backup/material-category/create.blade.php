@extends('layouts.app')

@section('title','ثبت دسته بندی مصالح')

@section('content')

<div class="container">

    <div class="card">

        <div class="card-header">

            ثبت دسته بندی مصالح

        </div>

        <div class="card-body">

            <form
                action="{{ route('material-category.store') }}"
                method="POST">

                @include('material-category._form')

            </form>

        </div>

    </div>

</div>

@endsection