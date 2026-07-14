@csrf

<div class="row">

    <div class="col-md-6 mb-3">

        <label class="form-label">

            عنوان

        </label>

        <input
            type="text"
            name="title"
            class="form-control"
            value="{{ old('title',$operationCategory->title ?? '') }}"
            required>

    </div>

    <div class="col-md-6 mb-3">

        <label class="form-label">

            کد

        </label>

        <input
            type="text"
            name="code"
            class="form-control"
            value="{{ old('code',$operationCategory->code ?? '') }}">

    </div>

    <div class="col-md-4 mb-3">

        <label>

            ترتیب نمایش

        </label>

        <input
            type="number"
            name="sort"
            class="form-control"
            value="{{ old('sort',$operationCategory->sort ?? 0) }}">

    </div>

    <div class="col-md-4 mb-3">

        <label>

            وضعیت

        </label>

        <select
            name="is_active"
            class="form-select">

            <option value="1"
                @selected(old('is_active',$operationCategory->is_active ?? 1)==1)>
                فعال
            </option>

            <option value="0"
                @selected(old('is_active',$operationCategory->is_active ?? 1)==0)>
                غیرفعال
            </option>

        </select>

    </div>

    <div class="col-md-12 mb-3">

        <label>

            توضیحات

        </label>

        <textarea
            name="description"
            rows="4"
            class="form-control">{{ old('description',$operationCategory->description ?? '') }}</textarea>

    </div>

</div>

<button class="btn btn-success">

    ذخیره اطلاعات

</button>

<a href="{{ route('operation-category.index') }}"
   class="btn btn-secondary">

    بازگشت

</a>