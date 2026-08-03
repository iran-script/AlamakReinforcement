@extends('layouts.app')

@section('title','ویرایش نقش')

@section('content')

<div class="page-eyebrow">ROLES / ویرایش</div>
<div class="page-heading">
    <h4><i class="bi bi-shield-lock text-primary"></i> ویرایش نقش: {{ $role->name }}</h4>
</div>

<form method="POST" action="{{ route('roles.update',$role) }}">
    @csrf
    @method('PUT')

    <div class="card-box p-3 p-md-4 mb-3" style="max-width: 560px;">
        @include('roles._form')
    </div>

    <div class="page-heading">
        <h5 class="mb-0"><i class="bi bi-key text-primary"></i> دسترسی‌ها</h5>
    </div>

    @foreach($groupedPermissions as $group => $permissions)

        <div class="card-box mb-3">

            <div class="card-header">
                <strong>{{ ucfirst($group) }}</strong>
            </div>

            <div class="card-body p-3">
                <div class="row">

                    @foreach($permissions as $permission)
                        <div class="col-6 col-md-4 col-lg-3 mb-2">
                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="permissions[]"
                                    value="{{ $permission->name }}"
                                    id="permission{{ $permission->id }}"
                                    {{ $role->hasPermissionTo($permission->name) ? 'checked' : '' }}>

                                <label class="form-check-label" for="permission{{ $permission->id }}">
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
        <i class="bi bi-check-circle"></i>
        ذخیره
    </button>

</form>

@endsection
