@extends('layouts.app')

@section('title','ویرایش نقش')

@section('content')

<div class="card-box">

<form method="POST"
      action="{{ route('roles.update',$role) }}">

    @csrf

    @method('PUT')

    @include('roles._form')

    <hr>

    <h5 class="mb-3">

        دسترسی ها

    </h5>

    @foreach($groupedPermissions as $group=>$permissions)

        <div class="card mb-3">

            <div class="card-header">

                <strong>

                    {{ ucfirst($group) }}

                </strong>

            </div>

            <div class="card-body">

                <div class="row">

                    @foreach($permissions as $permission)

                        <div class="col-md-3 mb-2">

                            <div class="form-check">

                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="permissions[]"
                                    value="{{ $permission->name }}"
                                    id="permission{{ $permission->id }}"
                                    {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }}>

                                <label
                                    class="form-check-label"
                                    for="permission{{ $permission->id }}">

                                    {{ $permission->name }}

                                </label>

                            </div>

                        </div>

                    @endforeach

                </div>

            </div>

        </div>

    @endforeach

    <button class="btn btn-primary">

        ذخیره

    </button>

</form>

</div>

@endsection