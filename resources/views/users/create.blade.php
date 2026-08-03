@extends('layouts.app')

@section('title', 'افزودن کاربر')

@section('content')

    <div class="page-eyebrow">USERS / جدید</div>

    <div class="page-heading">

        <h4>
            <i class="bi bi-person-plus text-primary"></i>
            افزودن کاربر جدید
        </h4>

        <a href="{{ route('users.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-right"></i>
            بازگشت
        </a>

    </div>



    <div class="card-box p-3 p-md-4" style="max-width:900px;">


        <form method="POST" action="{{ route('users.store') }}">

            @csrf


            <div class="row g-3">


                <div class="col-md-6">

                    <label class="form-label">
                        نام و نام خانوادگی
                    </label>

                    <input type="text" name="name" value="{{ old('name') }}"
                        class="form-control @error('name') is-invalid @enderror" required>


                    @error('name')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror

                </div>



                <div class="col-md-6">

                    <label class="form-label">
                        نام کاربری
                    </label>


                    <input type="text" name="username" value="{{ old('username') }}"
                        class="form-control @error('username') is-invalid @enderror" required>


                    @error('username')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror

                </div>




                <div class="col-md-6">

                    <label class="form-label">
                        ایمیل
                    </label>


                    <input type="email" name="email" value="{{ old('email') }}"
                        class="form-control @error('email') is-invalid @enderror" required>


                    @error('email')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror

                </div>





                <div class="col-md-3">

                    <label class="form-label">
                        نقش کاربر
                    </label>


                    <select name="role" class="form-select @error('role') is-invalid @enderror" required>


                        <option value="">
                            انتخاب نقش
                        </option>


                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" @selected(old('role') == $role->name)>

                                {{ $role->name }}

                            </option>
                        @endforeach


                    </select>


                    @error('role')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror


                </div>





                <div class="col-md-3">

                    <label class="form-label">
                        گروه کاربری
                    </label>


                    <select name="group_user_id" class="form-select @error('group_user_id') is-invalid @enderror">


                        <option value="">
                            بدون گروه
                        </option>


                        @foreach ($groupusers as $group)
                            <option value="{{ $group->id }}" @selected(old('group_user_id') == $group->id)>

                                {{ $group->name }}

                            </option>
                        @endforeach


                    </select>


                    @error('group_user_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror


                </div>






                <div class="col-md-6">

                    <label class="form-label">
                        زون کاربر
                    </label>


                    <select name="zone_id" class="form-select @error('zone_id') is-invalid @enderror">


                        <option value="">
                            بدون زون / دسترسی عمومی
                        </option>


                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}" @selected(old('zone_id') == $zone->id)>

                                {{ $zone->name }}

                            </option>
                        @endforeach


                    </select>


                    @error('zone_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror


                </div>

                <div class="col-md-6">

                    <label class="form-label">
                        پیمان کاربر
                    </label>


                    <select name="contract_id" class="form-select @error('contract_id') is-invalid @enderror">


                        <option value="">
                            بدون پیمان
                        </option>


                        @foreach ($contracts as $contract)
                            <option value="{{ $contract->id }}" @selected(old('contract_id') == $contract->id)>

                                {{ $contract->contract_number }} - {{ $contract->contractor_name }}

                            </option>
                        @endforeach


                    </select>


                    @error('contract_id')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror


                </div>





                <div class="col-md-6">

                    <label class="form-label">
                        رمز عبور
                    </label>


                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                        required>


                    @error('password')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                    @enderror


                </div>





                <div class="col-md-6">

                    <label class="form-label">
                        تکرار رمز عبور
                    </label>


                    <input type="password" name="password_confirmation" class="form-control" required>

                </div>


            </div>



            <button type="submit" class="btn btn-success mt-4">

                <i class="bi bi-check-circle"></i>

                ثبت کاربر

            </button>



        </form>


    </div>


@endsection
