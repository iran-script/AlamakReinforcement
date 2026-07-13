@extends('layouts.app')

@section('title', 'افزودن کاربر')

@section('content')

<div class="card-box">

```
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="mb-0">افزودن کاربر جدید</h3>

    <a href="{{ route('users.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-right"></i>
        بازگشت
    </a>
</div>

<form method="POST" action="{{ route('users.store') }}">
    @csrf

    <div class="row">
        <div class="col-md-6 mb-3">
            <label for="name" class="form-label">نام و نام خانوادگی</label>

            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name') }}"
                class="form-control @error('name') is-invalid @enderror"
                required
            >

            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6 mb-3">
            <label for="username" class="form-label">نام کاربری</label>

            <input
                id="username"
                type="text"
                name="username"
                value="{{ old('username') }}"
                class="form-control @error('username') is-invalid @enderror"
                required
            >

            @error('username')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6 mb-3">
            <label for="email" class="form-label">ایمیل</label>

            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                class="form-control @error('email') is-invalid @enderror"
                required
            >

            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3 mb-3">
            <label for="role" class="form-label">نقش کاربر</label>

            <select
                id="role"
                name="role"
                class="form-select @error('role') is-invalid @enderror"
                required
            >
                <option value="">انتخاب نقش</option>

                @foreach($roles as $role)
                    <option
                        value="{{ $role->name }}"
                        @selected(old('role') === $role->name)
                    >
                        {{ $role->name }}
                    </option>
                @endforeach
            </select>

            @error('role')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-3 mb-3">
            <label for="zone_id" class="form-label">زون کاربر</label>

            <select
                id="zone_id"
                name="zone_id"
                class="form-select @error('zone_id') is-invalid @enderror"
            >
                <option value="">بدون زون / دسترسی عمومی</option>

                @foreach($zones as $zone)
                    <option
                        value="{{ $zone->id }}"
                        @selected((string) old('zone_id') === (string) $zone->id)
                    >
                        {{ $zone->name }}
                    </option>
                @endforeach
            </select>

            @error('zone_id')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6 mb-3">
            <label for="password" class="form-label">رمز عبور</label>

            <input
                id="password"
                type="password"
                name="password"
                class="form-control @error('password') is-invalid @enderror"
                required
            >

            @error('password')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6 mb-3">
            <label for="password_confirmation" class="form-label">
                تکرار رمز عبور
            </label>

            <input
                id="password_confirmation"
                type="password"
                name="password_confirmation"
                class="form-control"
                required
            >
        </div>
    </div>

    <button type="submit" class="btn btn-success">
        <i class="bi bi-check-circle"></i>
        ثبت کاربر
    </button>
</form>
```

</div>
@endsection
