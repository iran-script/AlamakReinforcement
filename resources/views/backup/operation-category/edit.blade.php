@extends('layouts.app')

@section('title','ویرایش دسته بندی عملیات')

@section('content')

<div class="container">

    <div class="card">

        <div class="card-header">

            ویرایش دسته بندی عملیات

        </div>

        <div class="card-body">

            <form
                action="{{ route('operation-category.update',$operationCategory) }}"
                method="POST">

                @method('PUT')

                @include('operation-category._form')

            </form>

        </div>

    </div>

</div>

@endsection