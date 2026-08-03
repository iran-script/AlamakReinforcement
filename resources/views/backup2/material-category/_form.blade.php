@csrf

<div class="row g-3">

    <div class="col-md-6">
        <label class="form-label">عنوان</label>
        <input
            type="text"
            name="title"
            class="form-control"
            value="{{ old('title',$materialCategory->title ?? '') }}"
            required>
    </div>

    <div class="col-md-6">
        <label class="form-label">کد</label>
        <input
            type="text"
            name="code"
            class="form-control"
            value="{{ old('code',$materialCategory->code ?? '') }}">
    </div>

    <div class="col-md-4">
        <label class="form-label">ترتیب</label>
        <input
            type="number"
            name="sort"
            class="form-control"
            value="{{ old('sort',$materialCategory->sort ?? 0) }}">
    </div>

    <div class="col-md-4">
        <label class="form-label">وضعیت</label>
        <select name="is_active" class="form-select">
            <option value="1" @selected(old('is_active',$materialCategory->is_active ?? 1)==1)>فعال</option>
            <option value="0" @selected(old('is_active',$materialCategory->is_active ?? 1)==0)>غیرفعال</option>
        </select>
    </div>

    <div class="col-12">
        <label class="form-label">توضیحات</label>
        <textarea
            class="form-control"
            rows="4"
            name="description">{{ old('description',$materialCategory->description ?? '') }}</textarea>
    </div>

</div>

<div class="mt-4 d-flex gap-2">
    <button class="btn btn-success">
        <i class="bi bi-check-circle"></i>
        ذخیره
    </button>

    <a href="{{ route('material-category.index') }}" class="btn btn-secondary">
        بازگشت
    </a>
</div>
