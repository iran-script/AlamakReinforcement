@extends('layouts.app')

@section('title', 'افزودن گروه کاربران')

@section('content')

<div class="page-eyebrow">GROUPUSER</div>

<div class="page-heading">
    <h4>
        <i class="bi bi-people-fill text-primary"></i>
        افزودن گروه کاربران
    </h4>

    <a href="{{ route('groupusers.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-right"></i>
        بازگشت
    </a>
</div>


<div class="card-box">

    <div class="card-body p-4">


        <form method="POST" action="{{ route('groupusers.store') }}">

            @csrf


            <div class="mb-3">

                <label class="form-label">
                    نام گروه
                </label>

                <input type="text"
                       name="name"
                       class="form-control @error('name') is-invalid @enderror"
                       value="{{ old('name') }}"
                       required>


                @error('name')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror

            </div>



            <div class="mb-3">

                <label class="form-label">
                    توضیحات
                </label>

                <textarea name="description"
                          rows="4"
                          class="form-control @error('description') is-invalid @enderror">{{ old('description') }}</textarea>


                @error('description')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                @enderror

            </div>



            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i>
                ذخیره گروه
            </button>


        </form>


    </div>

</div>

@endsection