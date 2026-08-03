@csrf

<div class="row g-3">

    <div class="col-md-6">
        <label class="form-label">نام پیمانکار</label>
        <input type="text" name="contractor_name" class="form-control"
            value="{{ old('contractor_name', $contract->contractor_name ?? '') }}" required>

        @error('contractor_name')
            <div class="text-danger mt-1">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">شماره پیمان</label>
        <input type="text" name="contract_number" class="form-control"
            value="{{ old('contract_number', $contract->contract_number ?? '') }}" required>

        @error('contract_number')
            <div class="text-danger mt-1">{{ $message }}</div>
        @enderror
    </div>

</div>

<div class="row g-3 mt-0">

    <div class="col-md-6">
        <label class="form-label">نام ناظر</label>

        <select name="supervisor_id" class="form-select" required>

            <option value="">انتخاب ناظر</option>

            @foreach ($supervisors as $supervisor)
                <option value="{{ $supervisor->id }}" @selected(old('supervisor_id', $contract->supervisor_id ?? '') == $supervisor->id)>
                    {{ $supervisor->name }}
                </option>
            @endforeach

        </select>

        @error('supervisor_id')
            <div class="text-danger mt-1">{{ $message }}</div>
        @enderror
    </div>

</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-success">
        <i class="bi bi-check-circle"></i>
        ذخیره اطلاعات
    </button>

    <a href="{{ route('contract.index') }}" class="btn btn-secondary">
        بازگشت
    </a>
</div>
