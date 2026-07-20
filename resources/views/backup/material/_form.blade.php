@csrf

<div class="row">

    <div class="col-md-6 mb-3">

        <label class="form-label">

            دسته بندی مصالح

        </label>

        <select
            name="material_category_id"
            class="form-select"
            required>

            <option value="">

                انتخاب کنید

            </option>

            @foreach($categories as $category)

                <option
                    value="{{ $category->id }}"
                    @selected(old(
                        'material_category_id',
                        $material->material_category_id ?? ''
                    ) == $category->id)>

                    {{ $category->title }}

                </option>

            @endforeach

        </select>

        @error('material_category_id')

            <div class="text-danger">

                {{ $message }}

            </div>

        @enderror

    </div>

    <div class="col-md-6 mb-3">

        <label class="form-label">

            نام مصالح

        </label>

        <input
            type="text"
            name="title"
            class="form-control"
            value="{{ old('title',$material->title ?? '') }}"
            required>

        @error('title')

            <div class="text-danger">

                {{ $message }}

            </div>

        @enderror

    </div>

</div>

<div class="row">

    <div class="col-md-4 mb-3">

        <label class="form-label">

            کد کالا

        </label>

        <input
            type="text"
            name="code"
            class="form-control"
            value="{{ old('code',$material->code ?? '') }}">

    </div>

    <div class="col-md-4 mb-3">

        <label class="form-label">

            واحد

        </label>

        <input
            type="text"
            name="unit"
            class="form-control"
            value="{{ old('unit',$material->unit ?? 'عدد') }}"
            placeholder="عدد / متر / کیلوگرم">

    </div>

    <div class="col-md-4 mb-3">

        <label class="form-label">

            قیمت پیش فرض

        </label>

        <input
            type="number"
            min="0"
            step="0.01"
            name="default_price"
            class="form-control"
            value="{{ old('default_price',$material->default_price ?? 0) }}">

    </div>

</div>

<div class="row">

    <div class="col-md-4 mb-3">

        <label class="form-label">

            وضعیت

        </label>

        <select
            name="is_active"
            class="form-select">

            <option
                value="1"
                @selected(old(
                    'is_active',
                    $material->is_active ?? 1
                )==1)>

                فعال

            </option>

            <option
                value="0"
                @selected(old(
                    'is_active',
                    $material->is_active ?? 1
                )==0)>

                غیرفعال

            </option>

        </select>

    </div>

</div>

<div class="mb-3">

    <label class="form-label">

        توضیحات

    </label>

    <textarea
        name="description"
        rows="4"
        class="form-control">{{ old('description',$material->description ?? '') }}</textarea>

</div>

<div class="mt-4">

    <button
        type="submit"
        class="btn btn-success">

        <i class="bi bi-check-circle"></i>

        ذخیره اطلاعات

    </button>

    <a
        href="{{ route('material.index') }}"
        class="btn btn-secondary">

        بازگشت

    </a>

</div>