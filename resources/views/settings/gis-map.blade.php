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

    <link href="{{ url('css/ol.css') }}" rel="stylesheet">
    <script src="{{ asset('js/ol.js') }}"></script>
    <style>
        .map-profile-card { border: 1px solid var(--border); border-radius: 12px; padding: 16px; background: var(--surface); }
        .map-profile-preview { height: 310px; border-radius: 10px; overflow: hidden; background: #eef2f4; }
        .map-profile-url { direction: ltr; text-align: left; overflow-wrap: anywhere; }
        .map-profile-form { border-top: 1px solid var(--border); margin-top: 14px; padding-top: 14px; }
        @media (max-width: 767px) { .map-profile-preview { height: 240px; } }
    </style>

    <section class="card mb-3" aria-labelledby="map-profiles-title">
        <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <div><h5 id="map-profiles-title" class="mb-1">پروفایل‌های نقشه پایه</h5><p class="text-muted mb-0">پروفایل فعال، فقط نقشه پایه را تغییر می‌دهد؛ لایه MVT علمک‌ها مستقل می‌ماند.</p></div>
            <span class="badge text-bg-info">{{ $profiles->count() }} پروفایل</span>
        </div>
        <div class="card-body d-grid gap-3">
            @forelse($profiles as $profile)
                <article class="map-profile-card">
                    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                        <div>
                            <h6 class="mb-1">{{ $profile->name }} @if($profile->is_active)<span class="badge text-bg-success">فعال</span>@endif</h6>
                            <div class="small text-muted">{{ $profile->service_type === 'vector_mvt' ? 'Vector MVT' : 'XYZ Raster' }} · Zoom {{ $profile->min_zoom }}–{{ $profile->max_zoom }}</div>
                            <code class="d-block map-profile-url mt-2">{{ $profile->base_tile_url }}</code>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            @unless($profile->is_active)
                                <form method="POST" action="{{ route('settings.gis.profiles.activate', $profile) }}">@csrf<button class="btn btn-success btn-sm" type="submit">فعال‌سازی</button></form>
                                <form method="POST" action="{{ route('settings.gis.profiles.destroy', $profile) }}" onsubmit="return confirm('این پروفایل حذف شود؟')">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm" type="submit">حذف</button></form>
                            @endunless
                        </div>
                    </div>
                    <details class="mt-3">
                        <summary class="btn btn-outline-secondary btn-sm">ویرایش پروفایل</summary>
                        <form class="map-profile-form row g-3" method="POST" action="{{ route('settings.gis.profiles.update', $profile) }}">
                            @csrf @method('PUT')
                            @include('settings.partials.map-profile-fields', ['profile' => $profile, 'prefix' => 'edit-'.$profile->id])
                            <div class="col-12"><button class="btn btn-primary" type="submit">ذخیره تغییرات</button></div>
                        </form>
                    </details>
                </article>
            @empty
                <div class="alert alert-info mb-0">هنوز پروفایلی ثبت نشده است؛ پروفایل نخست به‌صورت خودکار فعال می‌شود.</div>
            @endforelse

            <details class="map-profile-card" @if($errors->hasAny(['name','service_type','base_tile_url','style_url','min_zoom','max_zoom'])) open @endif>
                <summary class="btn btn-primary">افزودن پروفایل نقشه</summary>
                <form class="map-profile-form row g-3" method="POST" action="{{ route('settings.gis.profiles.store') }}">
                    @csrf
                    @include('settings.partials.map-profile-fields', ['profile' => null, 'prefix' => 'new'])
                    <div class="col-12"><button class="btn btn-primary" type="submit">ثبت پروفایل</button></div>
                </form>
            </details>
        </div>
    </section>

    <section class="card mb-3" aria-labelledby="map-preview-title">
        <div class="card-header"><h5 id="map-preview-title" class="mb-0">پیش‌نمایش و تست اتصال</h5></div>
        <div class="card-body">
            <div class="row g-2 align-items-end mb-3">
                <div class="col-md-3"><label class="form-label" for="preview_type">نوع سرویس</label><select id="preview_type" class="form-select"><option value="xyz_raster">XYZ Raster</option><option value="vector_mvt">Vector MVT</option></select></div>
                <div class="col-md-7"><label class="form-label" for="preview_url">Tile URL</label><input id="preview_url" class="form-control map-profile-url" dir="ltr" value="{{ $setting?->base_tile_url ?? config('gis.default_base_tile_url') }}"></div>
                <div class="col-md-2"><button id="test-map-service" type="button" class="btn btn-outline-primary w-100">تست اتصال</button></div>
            </div>
            <div id="map-profile-preview" class="map-profile-preview" role="img" aria-label="پیش‌نمایش سرویس نقشه"></div>
            <p id="map-profile-test-status" class="small mt-2 mb-0" role="status" aria-live="polite">برای بررسی دسترسی Tile از همین مرورگر، دکمه تست اتصال را بزنید.</p>
            <p class="small text-muted mt-2 mb-0">در Production آدرس سرویس و منابع Style باید داخلی باشند. تست از مرورگر انجام می‌شود و Backend برای URL دلخواه درخواست شبکه ارسال نمی‌کند.</p>
        </div>
    </section>

    <script>
        (() => {
            const preview = document.getElementById('map-profile-preview');
            const type = document.getElementById('preview_type');
            const url = document.getElementById('preview_url');
            const status = document.getElementById('map-profile-test-status');
            const button = document.getElementById('test-map-service');
            let previewMap;
            const makeLayer = () => {
                const source = type.value === 'vector_mvt'
                    ? new ol.source.VectorTile({format: new ol.format.MVT(), url: url.value})
                    : new ol.source.XYZ({url: url.value});
                const layer = type.value === 'vector_mvt'
                    ? new ol.layer.VectorTile({source, style: new ol.style.Style({stroke: new ol.style.Stroke({color:'#64748b',width:1}), fill: new ol.style.Fill({color:'rgba(148,163,184,.25)'})})})
                    : new ol.layer.Tile({source});
                return {source, layer};
            };
            const initial = makeLayer();
            previewMap = new ol.Map({target:preview,layers:[initial.layer],view:new ol.View({center:ol.proj.fromLonLat([51.4,35.7]),zoom:5,minZoom:0,maxZoom:22})});
            button.addEventListener('click', async () => {
                status.textContent = 'در حال بررسی دریافت Tile از مرورگر…';
                button.disabled = true;
                const policyResponse = await fetch(@json(route('settings.gis.test-service')), {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token())},
                    body: JSON.stringify({service_type: type.value, base_tile_url: url.value})
                }).catch(() => null);
                if (!policyResponse) {
                    status.textContent = 'امکان بررسی سیاست امنیتی سرویس وجود ندارد.';
                    button.disabled = false;
                    return;
                }
                const policy = await policyResponse.json().catch(() => ({}));
                if (!policyResponse.ok || policy.valid !== true) {
                    status.textContent = Object.values(policy.errors || {}).flat()[0] || 'آدرس سرویس با سیاست امنیتی سازگار نیست.';
                    button.disabled = false;
                    return;
                }
                const {source: testSource, layer} = makeLayer();
                let finished = false;
                const done = (ok, message) => {
                    if (finished) return;
                    finished = true;
                    clearTimeout(timer);
                    testSource.un('tileloadend', success);
                    testSource.un('tileloaderror', failure);
                    status.textContent = message;
                    button.disabled = false;
                };
                const success = () => done(true, 'اتصال موفق است و Tile از سرویس انتخاب‌شده دریافت شد.');
                const failure = () => done(false, 'Tile دریافت نشد؛ URL، دسترسی شبکه یا CORS سرویس را بررسی کنید.');
                testSource.on('tileloadend', success);
                testSource.on('tileloaderror', failure);
                previewMap.setLayers([layer]);
                previewMap.getView().setZoom(5);
                previewMap.renderSync();
                const timer = setTimeout(() => done(false, 'در مهلت تعیین‌شده پاسخی از سرویس دریافت نشد.'), 8000);
            });
        })();
    </script>

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
