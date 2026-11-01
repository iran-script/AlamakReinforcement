<div class="col-md-4">
    <label class="form-label" for="{{ $prefix }}-name">نام پروفایل</label>
    <input class="form-control" id="{{ $prefix }}-name" name="name" maxlength="120" required value="{{ old('name', $profile?->name) }}">
    @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
<div class="col-md-4">
    <label class="form-label" for="{{ $prefix }}-type">نوع سرویس</label>
    <select class="form-select" id="{{ $prefix }}-type" name="service_type" required>
        <option value="xyz_raster" @selected(old('service_type', $profile?->service_type ?? 'xyz_raster') === 'xyz_raster')>XYZ Raster</option>
        <option value="vector_mvt" @selected(old('service_type', $profile?->service_type) === 'vector_mvt')>Vector MVT</option>
    </select>
    @error('service_type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
<div class="col-md-4">
    <label class="form-label" for="{{ $prefix }}-attribution">Attribution</label>
    <input class="form-control" id="{{ $prefix }}-attribution" name="base_tile_attribution" maxlength="500" value="{{ old('base_tile_attribution', $profile?->base_tile_attribution) }}">
    @error('base_tile_attribution')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
<div class="col-12">
    <label class="form-label" for="{{ $prefix }}-url">Tile URL</label>
    <input class="form-control map-profile-url" dir="ltr" id="{{ $prefix }}-url" name="base_tile_url" maxlength="1000" required value="{{ old('base_tile_url', $profile?->base_tile_url) }}" placeholder="/tiles/{z}/{x}/{y}.png">
    <div class="form-text">از {z}، {x} و {y} استفاده کنید. Query string و توکن در URL پذیرفته نمی‌شود.</div>
    @error('base_tile_url')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
<div class="col-md-8">
    <label class="form-label" for="{{ $prefix }}-style">Style URL (اختیاری)</label>
    <input class="form-control map-profile-url" dir="ltr" id="{{ $prefix }}-style" name="style_url" maxlength="1000" value="{{ old('style_url', $profile?->style_url) }}" placeholder="/maps/styles/base/style.json">
    @error('style_url')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
<div class="col-md-2">
    <label class="form-label" for="{{ $prefix }}-min">کمینه Zoom</label>
    <input class="form-control" id="{{ $prefix }}-min" type="number" name="min_zoom" min="0" max="22" required value="{{ old('min_zoom', $profile?->min_zoom ?? 0) }}">
    @error('min_zoom')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
<div class="col-md-2">
    <label class="form-label" for="{{ $prefix }}-max">بیشینه Zoom</label>
    <input class="form-control" id="{{ $prefix }}-max" type="number" name="max_zoom" min="0" max="22" required value="{{ old('max_zoom', $profile?->max_zoom ?? 22) }}">
    @error('max_zoom')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
</div>
