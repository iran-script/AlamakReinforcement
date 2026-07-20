@extends('layouts.app')

@section('title','ویرایش مصالح')

@section('content')

<div class="container-fluid">

    <div class="card shadow">

        <div class="card-header">

            <h5 class="mb-0">

                ویرایش مصالح

            </h5>

        </div>

        <div class="card-body">

            @if($errors->any())

                <div class="alert alert-danger">

                    <ul class="mb-0">

                        @foreach($errors->all() as $error)

                            <li>{{ $error }}</li>

                        @endforeach

                    </ul>

                </div>

            @endif

            <form
                action="{{ route('material.update',$material) }}"
                method="POST">

                @csrf

                @method('PUT')

                @include('material._form')

            </form>

        </div>

    </div>

</div>

@endsection