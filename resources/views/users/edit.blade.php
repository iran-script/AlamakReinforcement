@extends('layouts.app')

@section('title', 'ویرایش کاربر')

@section('content')

<div class="page-eyebrow">USERS / ویرایش</div>

<div class="page-heading">

    <h4>
        <i class="bi bi-person-gear text-primary"></i>
        ویرایش کاربر: {{ $user->name }}
    </h4>


    <a href="{{ route('users.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-right"></i>
        بازگشت
    </a>

</div>



<div class="card-box p-3 p-md-4" style="max-width:900px;">


<form method="POST" action="{{ route('users.update',$user) }}">

@csrf
@method('PUT')

@if($user->organization_id === null && (($user->groupUser && $user->groupUser->organization_id !== null) || ($user->zone && $user->zone->organization_id !== null)))
<div class="alert alert-warning">این کاربر به GroupUser یا Zone سازمانی متصل است اما سازمان کاربر خالی است؛ قبل از ذخیره، سازمان سازگار را انتخاب کنید.</div>
@endif


<div class="row g-3">

@if($isSuperAdmin)
<div class="col-md-6">
    <label class="form-label" for="organization_id">سازمان</label>
    <select id="organization_id" name="organization_id" class="form-select @error('organization_id') is-invalid @enderror">
        <option value="">بدون سازمان</option>
        @foreach($organizations as $organization)
            <option value="{{ $organization->id }}" @selected(old('organization_id', $user->organization_id) == $organization->id)>{{ $organization->name }} ({{ $organization->code }})</option>
        @endforeach
    </select>
    @error('organization_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text">سازمان GroupUser و Zone باید با این مقدار سازگار باشد.</div>
</div>
@endif



<div class="col-md-6">

<label class="form-label">
نام و نام خانوادگی
</label>

<input type="text"
       name="name"
       value="{{ old('name',$user->name) }}"
       class="form-control @error('name') is-invalid @enderror"
       required>


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


<input type="text"
       name="username"
       value="{{ old('username',$user->username) }}"
       class="form-control @error('username') is-invalid @enderror"
       required>


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


<input type="email"
       name="email"
       value="{{ old('email',$user->email) }}"
       class="form-control @error('email') is-invalid @enderror"
       required>


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


<select name="role"
        class="form-select @error('role') is-invalid @enderror"
        required>


<option value="">
انتخاب نقش
</option>


@foreach($roles as $role)

<option value="{{ $role->name }}"
@selected(old('role',$user->roles->first()?->name)==$role->name)>

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


<select name="group_user_id"
        class="form-select @error('group_user_id') is-invalid @enderror">


<option value="">
بدون گروه
</option>


@foreach($groupusers as $group)

<option value="{{ $group->id }}" data-organization="{{ $group->organization_id }}"
@selected(old('group_user_id',$user->group_user_id)==$group->id)>

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


<select name="zone_id"
        class="form-select @error('zone_id') is-invalid @enderror" @required($isBoss)>


<option value="">
بدون زون / دسترسی عمومی
</option>


@foreach($zones as $zone)

<option value="{{ $zone->id }}" data-organization="{{ $zone->organization_id }}"
@selected(old('zone_id',$user->zone_id)==$zone->id)>

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


<select name="contract_id"
        class="form-select @error('contract_id') is-invalid @enderror">


<option value="">
بدون پیمان
</option>


@foreach($contracts as $contract)

<option value="{{ $contract->id }}"
@selected(old('contract_id',$user->contract_id)==$contract->id)>

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

رمز عبور جدید

<small class="text-muted">
(در صورت عدم تغییر خالی بگذار)
</small>

</label>


<input type="password"
       name="password"
       class="form-control @error('password') is-invalid @enderror">


@error('password')

<div class="invalid-feedback">
{{ $message }}
</div>

@enderror


</div>







<div class="col-md-6">


<label class="form-label">
تکرار رمز عبور جدید
</label>


<input type="password"
       name="password_confirmation"
       class="form-control">


</div>



</div>




<button type="submit" class="btn btn-primary mt-4">

<i class="bi bi-save"></i>

ذخیره تغییرات

</button>



</form>


</div>


@endsection

@push('scripts')
@if($isSuperAdmin)
<script>
(() => {
    const organization = document.getElementById('organization_id');
    const filter = () => {
        ['group_user_id', 'zone_id'].forEach(id => {
            const select = document.querySelector(`[name="${id}"]`);
            if (!select) return;
            [...select.options].forEach(option => {
                if (!option.value) return;
                const visible = !organization.value || option.dataset.organization === organization.value;
                option.hidden = !visible;
                if (!visible) option.selected = false;
            });
        });
    };
    organization.addEventListener('change', filter);
    filter();
})();
</script>
@endif
@endpush
