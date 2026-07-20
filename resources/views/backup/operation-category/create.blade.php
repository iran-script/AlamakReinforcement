@extends('layouts.app')

@section('title','ثبت دسته بندی عملیات')

@section('content')

<div class="container">

    <div class="card">

        <div class="card-header">

            ثبت دسته بندی عملیات

        </div>

        <div class="card-body">

            <form
                action="{{ route('operation-category.store') }}"
                method="POST">

                @include('operation-category._form')

            </form>

        </div>

    </div>

</div>

@endsection