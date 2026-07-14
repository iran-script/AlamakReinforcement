@extends('layouts.app')

@section('title','ویرایش دسته بندی مصالح')

@section('content')

<div class="container">

    <div class="card">

        <div class="card-header">

            ویرایش دسته بندی مصالح

        </div>

        <div class="card-body">

            <form
                action="{{ route('material-category.update',$materialCategory) }}"
                method="POST">

                @method('PUT')

                @include('material-category._form')

            </form>

        </div>

    </div>

</div>

@endsection