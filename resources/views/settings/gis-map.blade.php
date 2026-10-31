@extends('layouts.app')

@section('title', 'تنظیمات نقشه GIS')

@section('content')
    <div class="page-eyebrow">SETTINGS / GIS</div>
    <div class="page-heading">
        <div><h3><i class="bi bi-map"></i> تنظیمات نقشه پایه</h3><p class="text-muted mb-0">آدرس سرویس کاشی نقشه پایه را برای سامانه تنظیم کنید.</p></div>
        <a href="{{ route('map') }}" class="btn btn-secondary">مشاهده نقشه</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="card"><div class="card-header">سرویس Base Tile</div><div class="card-body">
        <form method="POST" action="{{ route('settings.gis.update') }}">
            @csrf @method('PUT')
            <div class="mb-3"><label class="form-label" for="base_tile_url">آدرس Tile Map</label><input class="form-control" dir="ltr" id="base_tile_url" name="base_tile_url" required maxlength="1000" value="{{ old('base_tile_url', $setting?->base_tile_url ?? config('gis.default_base_tile_url')) }}" placeholder="https://server.example/{z}/{x}/{y}.png"><div class="form-text">آدرس باید شامل هر سه پارامتر <code>{z}</code>، <code>{x}</code> و <code>{y}</code> باشد. آدرس HTTPS یا مسیر داخلی سامانه پذیرفته می‌شود.</div></div>
            <div class="mb-3"><label class="form-label" for="base_tile_attribution">متن منبع نقشه</label><input class="form-control" id="base_tile_attribution" name="base_tile_attribution" maxlength="500" value="{{ old('base_tile_attribution', $setting?->base_tile_attribution) }}"></div>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" @checked(old('is_active', $setting?->is_active ?? true))><label class="form-check-label" for="is_active">استفاده از این نقشه پایه</label></div>
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg"></i> ذخیره تنظیمات</button>
        </form>
    </div></div>

    <div class="card mt-3">
        <div class="card-header d-flex align-items-center justify-content-between gap-2">
            <span>لایه‌های نقشه</span>
            <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#add-gis-layer" aria-expanded="{{ $errors->hasAny(['key','title','type','endpoint','wms_layers']) ? 'true' : 'false' }}" aria-controls="add-gis-layer">
                <i class="bi bi-plus-lg"></i> افزودن لایه
            </button>
        </div>
        <div class="card-body">
            <div id="add-gis-layer" class="collapse {{ $errors->hasAny(['key','title','type','endpoint','wms_layers']) ? 'show' : '' }} mb-4">
                <div class="border rounded-3 p-3 bg-light">
                    <h6 class="mb-3">لایه جدید</h6>
                    <form method="POST" action="{{ route('settings.gis.layers.store') }}" id="gis-layer-form">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label" for="layer_title">عنوان</label><input class="form-control" id="layer_title" name="title" maxlength="200" required value="{{ old('title') }}"></div>
                            <div class="col-md-4"><label class="form-label" for="layer_key">کلید</label><input class="form-control" dir="ltr" id="layer_key" name="key" maxlength="100" pattern="[a-z][a-z0-9_]*" required value="{{ old('key') }}" placeholder="service_zones"></div>
                            <div class="col-md-4"><label class="form-label" for="layer_type">نوع لایه</label><select class="form-select" id="layer_type" name="type" required><option value="vector_tile" @selected(old('type') === 'vector_tile')>Vector Tile (MVT)</option><option value="wms" @selected(old('type') === 'wms')>WMS</option></select></div>
                            <div class="col-12"><label class="form-label" for="layer_endpoint">آدرس سرویس</label><input class="form-control" dir="ltr" id="layer_endpoint" name="endpoint" maxlength="1000" required value="{{ old('endpoint') }}" placeholder="https://server.example/geoserver/wms"></div>
                            <div class="col-md-6 wms-setting"><label class="form-label" for="wms_layers">نام لایه WMS</label><input class="form-control" dir="ltr" id="wms_layers" name="wms_layers" maxlength="500" value="{{ old('wms_layers') }}" placeholder="workspace:service_zones"></div>
                            <div class="col-md-3 wms-setting"><label class="form-label" for="layer_format">فرمت</label><select class="form-select" id="layer_format" name="format"><option value="image/png">PNG</option><option value="image/jpeg">JPEG</option><option value="image/webp">WebP</option></select></div>
                            <div class="col-md-3 wms-setting"><label class="form-label" for="layer_version">نسخه WMS</label><select class="form-select" id="layer_version" name="version"><option value="1.3.0">1.3.0</option><option value="1.1.1">1.1.1</option></select></div>
                            <div class="col-md-4 wms-setting"><label class="form-label" for="server_type">نوع سرور</label><select class="form-select" id="server_type" name="server_type"><option value="">عمومی</option><option value="geoserver">GeoServer</option><option value="mapserver">MapServer</option><option value="qgis">QGIS Server</option></select></div>
                            <div class="col-md-4"><label class="form-label" for="min_zoom">کمترین زوم</label><input class="form-control" type="number" min="0" max="30" id="min_zoom" name="min_zoom" value="{{ old('min_zoom', 0) }}" required></div>
                            <div class="col-md-4"><label class="form-label" for="max_zoom">بیشترین زوم</label><input class="form-control" type="number" min="0" max="30" id="max_zoom" name="max_zoom" value="{{ old('max_zoom', 22) }}" required></div>
                            <div class="col-12 d-flex gap-4"><div class="form-check wms-setting"><input class="form-check-input" type="checkbox" id="transparent" name="transparent" value="1" checked><label class="form-check-label" for="transparent">پس‌زمینه شفاف</label></div><div class="form-check"><input class="form-check-input" type="checkbox" id="layer_active" name="is_active" value="1" checked><label class="form-check-label" for="layer_active">لایه فعال باشد</label></div></div>
                        </div>
                        <div class="mt-3 d-flex gap-2"><button class="btn btn-success" type="submit"><i class="bi bi-check-lg"></i> ذخیره لایه</button><button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#add-gis-layer">انصراف</button></div>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>عنوان</th><th>کلید</th><th>نوع</th><th>آدرس</th><th>منبع</th><th></th></tr></thead>
                    <tbody>
                        @foreach(config('gis.layers', []) as $key => $layer)
                            <tr><td>{{ $layer['title'] ?? $key }}</td><td><code>{{ $key }}</code></td><td>{{ strtoupper($layer['type'] ?? '—') }}</td><td dir="ltr"><code>{{ $layer['endpoint'] ?? '—' }}</code></td><td><span class="badge text-bg-secondary">سیستمی</span></td><td><span class="text-muted small">از config</span></td></tr>
                        @endforeach
                        @foreach($layers as $layer)
                            <tr><td>{{ $layer->title }}</td><td><code>{{ $layer->key }}</code></td><td>{{ strtoupper($layer->type) }}</td><td dir="ltr"><code>{{ $layer->endpoint }}</code></td><td><span class="badge text-bg-primary">سازمانی</span></td><td><form method="POST" action="{{ route('settings.gis.layers.destroy', $layer) }}" onsubmit="return confirm('این لایه حذف شود؟')">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash"></i> حذف</button></form></td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const type = document.getElementById('layer_type');
            const wmsFields = document.querySelectorAll('.wms-setting');
            const wmsLayers = document.getElementById('wms_layers');
            const syncLayerFields = () => {
                const isWms = type.value === 'wms';
                wmsFields.forEach((field) => field.classList.toggle('d-none', !isWms));
                wmsLayers.required = isWms;
            };
            type.addEventListener('change', syncLayerFields);
            syncLayerFields();
        });
    </script>
@endsection
