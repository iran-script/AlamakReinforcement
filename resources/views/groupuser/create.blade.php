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

            <div class="row g-3 mb-3" dir="rtl">
                <div class="col-md-6"><label class="form-label" for="organization_id">سازمان گروه</label>
                    <select id="organization_id" name="organization_id" class="form-select" required>
                        <option value="">انتخاب سازمان</option>
                        @foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected(old('organization_id') == $organization->id)>{{ $organization->name }}</option>@endforeach
                    </select>@error('organization_id')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6"><label class="form-label" for="zone_id">Zone جغرافیایی گروه</label>
                    <select id="zone_id" name="zone_id" class="form-select" required>
                        <option value="">انتخاب Zone</option>
                        @foreach($zones as $zone)<option value="{{ $zone->id }}" data-organization="{{ $zone->organization_id }}" @selected(old('zone_id') == $zone->id)>{{ $zone->name }}</option>@endforeach
                    </select>@error('zone_id')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mb-3"><label class="form-label" for="boss_user_ids">رؤسای مجاز این گروه</label>
                <select id="boss_user_ids" name="boss_user_ids[]" class="form-select" multiple size="5">
                    @foreach($bosses as $boss)<option value="{{ $boss->id }}" data-zone="{{ $boss->zone_id }}" data-organization="{{ $boss->organization_id }}" @selected(collect(old('boss_user_ids', []))->contains($boss->id))>{{ $boss->name }} — {{ $boss->zone?->name ?? 'بدون Zone' }}</option>@endforeach
                </select><small class="text-muted">فقط Bossهای همان سازمان و Zone قابل انتخاب هستند. گروه اجرایی حساب ورود مستقل ندارد.</small>
                @error('boss_user_ids.*')<div class="text-danger small">{{ $message }}</div>@enderror
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

@push('scripts')
<script>
(() => {
    const organization = document.getElementById('organization_id');
    const zone = document.getElementById('zone_id');
    const bosses = document.getElementById('boss_user_ids');
    const filter = () => {
        [...zone.options].forEach(option => { if (option.value) option.hidden = option.dataset.organization !== organization.value; });
        [...bosses.options].forEach(option => { option.hidden = option.dataset.organization !== organization.value || option.dataset.zone !== zone.value; if(option.hidden) option.selected = false; });
    };
    organization.addEventListener('change', filter); zone.addEventListener('change', filter); filter();
})();
</script>
@endpush
