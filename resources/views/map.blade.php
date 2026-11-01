@extends('layouts.app')

@section('title', 'نقشه GIS')

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="{{ url('css/ol.css') }}" rel="stylesheet">
    <script src="{{ asset('js/ol.js') }}"></script>


    <style>
        .map-wrapper {
            position: relative;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
        }

        #map {
            width: 100%;
            height: calc(100vh - 200px);
            min-height: 420px;
        }

        /* TOOLBAR */
        .toolbar {
            position: absolute;
            top: 14px;
            left: 16px;
            right: 16px;
            z-index: 1000;

            background: rgba(255, 255, 255, .96);
            backdrop-filter: blur(6px);
            border: 1px solid var(--border);
            padding: 10px 14px;
            border-radius: 12px;

            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;

            box-shadow: var(--shadow-md);
            color: var(--text-muted);
            font-size: 13px;
        }

        .toolbar-label {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-weight: 700;
        }

        .toolbar select,
        .toolbar input {
            padding: 7px 10px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            font-size: 13px;
        }

        .toolbar select:focus,
        .toolbar input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 .15rem var(--primary-soft);
        }

        .toolbar .legend {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-inline-start: auto;
            font-size: 12px;
        }

        .toolbar .legend span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .toolbar .legend i.dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot-done {
            background: var(--success);
        }

        .dot-pending {
            background: var(--danger);
        }

        .maintenance-modal { position: fixed; inset: 0; z-index: 1080; display: none; align-items: center; justify-content: center; padding: 18px; background: rgba(15, 23, 42, .58); }
        .maintenance-modal.is-open { display: flex; }
        .maintenance-dialog { width: min(760px, 100%); max-height: 92vh; overflow: auto; border-radius: 16px; background: var(--surface); color: var(--text); box-shadow: var(--shadow-lg); padding: 20px; }
        .maintenance-preview { display: flex; gap: 8px; flex-wrap: wrap; }
        .maintenance-preview img { width: 72px; height: 72px; object-fit: cover; border-radius: 8px; }

        /* INFO PANEL */
        .info-panel {
            position: absolute;
            bottom: 18px;
            right: 18px;

            width: 290px;
            max-width: calc(100% - 36px);
            background: rgba(255, 255, 255, .97);
            backdrop-filter: blur(6px);
            border: 1px solid var(--border);
            padding: 15px;
            border-radius: 12px;

            z-index: 1000;
            box-shadow: var(--shadow-md);
        }

        .info-panel h6 {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            margin-bottom: 10px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .info-panel hr {
            border-color: var(--border);
            margin: 0 0 10px;
        }

        #info {
            color: var(--text);
            font-size: 13.5px;
            line-height: 1.9;
        }

        #info b {
            color: var(--text-muted);
            font-weight: 600;
        }

        #info .plate {
            margin-top: 2px;
        }

        @media (max-width: 575.98px) {
            .toolbar {
                position: static;
                margin-bottom: 12px;
                box-shadow: var(--shadow-sm);
            }

            .toolbar .legend {
                margin-inline-start: 0;
                width: 100%;
            }

            #map {
                height: 55vh;
            }

            .info-panel {
                position: static;
                width: 100%;
                max-width: none;
                margin-top: 12px;
            }
        }
    </style>

    <div class="page-eyebrow">GIS / نقشه عملیاتی</div>
    <div class="page-heading">
        <h4><i class="bi bi-geo-alt text-primary"></i> نقشه علمک‌ها</h4>
    </div>

    <div class="map-wrapper">

        <!-- TOOLBAR -->
        <div class="toolbar">

            <span class="toolbar-label"><i class="bi bi-sliders"></i> وضعیت:</span>
            <select id="statusFilter" onchange="applyStatusFilter()">
                <option value="all">همه</option>
                <option value="flagRiser"> نشانه گذاری شده</option>
                <option value="submitOperation">تعمیر شده</option>
                <option value="resolveLead"> تایید شده بازرسی فنی</option>
                <option value="error"> رد شده</option>
            </select>

            <select id="maintenanceStatusFilter" aria-label="وضعیت پرونده تعمیر" onchange="applyMaintenanceFilters()">
                <option value="">همه پرونده‌ها</option><option value="open">باز</option><option value="in_progress">در حال اجرا</option><option value="submitted">ارسال‌شده برای رئیس ناحیه</option><option value="returned">برگشتی</option><option value="completed">تکمیل‌شده</option>
            </select>
            <select id="maintenanceSupervisorFilter" aria-label="ناظر" onchange="applyMaintenanceFilters()">
                <option value="">همه ناظرهای مجاز</option>@foreach($maintenanceSupervisors as $supervisor)<option value="{{ $supervisor->id }}">{{ $supervisor->name }}</option>@endforeach
            </select>
            <select id="maintenanceZoneFilter" aria-label="ناحیه" onchange="applyMaintenanceFilters()">
                <option value="">همه Zoneهای مجاز</option>@foreach($maintenanceZones as $zone)<option value="{{ $zone->id }}">{{ $zone->name }}</option>@endforeach
            </select>
            <select id="maintenanceTypeFilter" aria-label="نوع تعمیر" onchange="applyMaintenanceFilters()">
                <option value="">همه انواع تعمیر</option>@foreach($operationCategories as $category)<option value="{{ $category->id }}">{{ $category->title }}</option>@endforeach
            </select>

            @if($gisLayers->isNotEmpty())
                <span class="toolbar-label"><i class="bi bi-layers"></i> لایه:</span>
                <select id="layerSwitcher" onchange="switchLayer()">
                    @foreach($gisLayers as $layer)<option value="{{ $layer['key'] }}">{{ $layer['title'] }}</option>@endforeach
                </select>
            @endif


            <button id="btnHideZone" type="button" class="btn btn-outline-danger btn-sm" title="حذف  ترسیمات">
                <i class="bi bi-scissors"></i>
                حذف ترسیمات
            </button>

            @if($isLocal)
                <button id="btnDemoRisers" type="button" class="btn btn-outline-primary btn-sm">نمایش علمک‌های آزمایشی</button>
                <span id="demoRiserStatus" role="status" aria-live="polite"></span>
            @endif

            

            <div class="legend">
                <span><i class="dot" style="background-color: #f59e0b;"></i> پرونده تعمیر باز</span>
                <span><i class="dot" style="background-color: #8b5cf6;"></i> ارسال‌شده برای رئیس ناحیه</span>
                <span><i class="dot" style="background-color: #ef4444;"></i> برگشت برای اصلاح</span>
                <span><i class="dot" style="background-color: #909494;"></i> پیش‌فرض</span>
                <span><i class="dot" style="background-color: #f1c40f;"></i>  علامت‌گذاری شده</span>
                <span><i class="dot" style="background-color: #2ecc71;"></i>  ثبت عمیرات </span>
                <span><i class="dot" style="background-color: #e74c3c;"></i>  در انتظار نشت یابی</span>
                
            </div>

            {{-- <input type="text" id="searchBox" placeholder="جستجوی کد..." style="margin-right: 30px">

        <button class="btn btn-primary btn-sm" onclick="applyFilter()">
            فیلتر
\\
        </button> --}}

        </div>

        <!-- MAP -->
        <div id="map"></div>

        <!-- INFO -->
        <div class="info-panel">
            <h6><i class="bi bi-geo-alt-fill text-primary"></i> اطلاعات عارضه</h6>
            <hr>
            <div id="info">روی یک نقطه کلیک کنید</div>
        </div>



    </div>

    @if($canFieldReport)
    <div id="maintenanceModal" class="maintenance-modal" role="dialog" aria-modal="true" aria-labelledby="maintenanceTitle">
        <section class="maintenance-dialog" dir="rtl">
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 id="maintenanceTitle" class="h5 mb-0">پرونده تعمیر علمک</h2><button type="button" class="btn-close ms-0" aria-label="بستن" onclick="closeMaintenanceModal()"></button></div>
            <p id="maintenanceRiserLabel" class="text-muted small"></p>
            <form id="maintenanceCaseForm">
                @csrf
                <input type="hidden" name="riser_id" id="maintenanceRiserId">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">نوع خرابی / عملیات پیشنهادی</label><select class="form-select" name="operation_category_id"><option value="">انتخاب نوع</option>@foreach($operationCategories as $category)<option value="{{ $category->id }}">{{ $category->title }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label class="form-label">اولویت</label><select class="form-select" name="priority" required><option>کم</option><option selected>متوسط</option><option>زیاد</option><option>فوری</option></select></div>
                    <div class="col-md-6"><label class="form-label">زمان شناسایی</label><input class="form-control" name="identified_at" type="datetime-local" required></div>
                    <div class="col-12"><label class="form-label">شرح خرابی</label><textarea class="form-control" name="description" rows="3" maxlength="10000"></textarea></div>
                    <div class="col-12"><label class="form-label">تصاویر قبل از تعمیر</label><input class="form-control" name="photos_before[]" type="file" accept="image/*" multiple><div class="maintenance-preview mt-2" data-preview-for="photos_before[]"></div></div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3"><button type="button" class="btn btn-outline-secondary" onclick="closeMaintenanceModal()">انصراف</button><button class="btn btn-warning" type="submit">ثبت نیاز به تعمیر</button></div>
            </form>
            <form id="maintenanceOperationForm" class="d-none">
                @csrf
                <div class="alert alert-info">اطلاعات اجرای گروه تعمیراتی را پس از انجام کار ثبت کنید.</div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">تاریخ واقعی تعمیر</label><input class="form-control" name="operation_date" type="date" required></div>
                    <div class="col-md-6"><label class="form-label">نوع عملیات</label><select class="form-select" name="operation_category_id"><option value="">مطابق پرونده</option>@foreach($operationCategories as $category)<option value="{{ $category->id }}">{{ $category->title }}</option>@endforeach</select></div>
                    <div class="col-12"><label class="form-label">شرح تعمیر</label><textarea class="form-control" name="description" rows="2" maxlength="10000"></textarea></div>
                    <div class="col-12"><label class="form-label">مصالح مصرف‌شده (مقدار و واحد)</label><div class="table-responsive"><table class="table table-sm"><thead><tr><th>مصالح</th><th>واحد</th><th>مقدار</th></tr></thead><tbody>@foreach($materials as $material)<tr><td>{{ $material->title }}</td><td>{{ $material->unit }}</td><td><input class="form-control form-control-sm" type="number" min="0" step="0.01" data-material-id="{{ $material->id }}" value="0"></td></tr>@endforeach</tbody></table></div></div>
                    <div class="col-12"><label class="form-label">تصاویر بعد از تعمیر</label><input class="form-control" name="photos_after[]" type="file" accept="image/*" multiple><div class="maintenance-preview mt-2" data-preview-for="photos_after[]"></div></div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-3"><button class="btn btn-primary" type="submit">ارسال گزارش به رئیس ناحیه</button></div>
            </form>
            <div id="maintenanceMessage" class="small mt-2" role="status" aria-live="polite"></div>
        </section>
    </div>
    @endif

    <script>
        const configuredLayers = @json($gisLayers->keyBy('key'));
        const baseMapDefinition = @json($baseMap);
        let currentLayer = @json($gisLayers->first()['key'] ?? null);

        const styleCache = {};
        const featureIndex = {};

        function getStyle(status, code, showCode) {

            const key = status + '_' + (code || '') + '_' + (showCode ? 'label' : 'no-label');

            const statusColors = {
                default: '#5c6161',
                pending: '#f1c40f',
                success: '#2ecc71',
                approved: '#2ecc71',
                rejected: '#e74c3c',
                flagRiser: '#f1c40f', // زرد
                submitOperation: '#2ecc71', // سبز
                resolveLead: '#e74c3c',
                error: '#e74721',
                'case-open': '#f59e0b', 'case-in_progress': '#3b82f6',
                'case-submitted': '#8b5cf6', 'case-returned': '#ef4444', 'case-completed': '#16a34a'
            };

            if (!styleCache[key]) {

                styleCache[key] = new ol.style.Style({

                    image: new ol.style.Circle({
                        radius: 13,
                        fill: new ol.style.Fill({
                            color: statusColors[status] || '#95a5a6'
                        }),
                        stroke: new ol.style.Stroke({
                            color: '#fff',
                            width: 3
                        })
                    }),

                    text: new ol.style.Text({
                        text: showCode ? (code || '') : '',
                        offsetY: -16,
                font: '600 12px Tahoma, "Segoe UI", Arial',
                        fill: new ol.style.Fill({
                            color: '#1F2430'
                        }),
                        stroke: new ol.style.Stroke({
                            color: '#ffffff',
                            width: 3
                        })
                    })
                });
            }

            return styleCache[key];
        }

        function createOperationalLayer(layerKey) {
            const definition = configuredLayers[layerKey];

            if (!definition) {
                return null;
            }

            const commonOptions = {
                minZoom: definition.min_zoom,
                maxZoom: definition.max_zoom
            };

            if (definition.type === 'wms') {
                const wms = definition.wms;

                return new ol.layer.Tile({
                    ...commonOptions,
                    source: new ol.source.TileWMS({
                        url: definition.endpoint,
                        params: {
                            'LAYERS': wms.layers,
                            'FORMAT': wms.format,
                            'TRANSPARENT': wms.transparent,
                            'VERSION': wms.version,
                            'STYLES': wms.styles,
                            'TILED': true
                        },
                        serverType: wms.server_type || undefined
                    })
                });
            }

            return new ol.layer.VectorTile({
                ...commonOptions,
                declutter: true,
                source: new ol.source.VectorTile({
                    format: new ol.format.MVT(),
                    url: definition.endpoint,
                    cacheSize: 512
                }),
                style: function(feature) {
                    const code = feature.get('code');

                    if (code) {
                        const key = currentLayer + '_' + code;
                        featureIndex[key] = feature;
                    }

                    const visualStatus = feature.get('case_status') ? `case-${feature.get('case_status')}` : feature.get('status');
                    return getStyle(visualStatus, code, map.getView().getZoom() >= 14);
                }
            });
        }

        let operationalLayer = createOperationalLayer(currentLayer);
        function createBaseMapLayer(definition) {
            const common = { minZoom: definition.min_zoom, maxZoom: definition.max_zoom };
            if (definition.service_type === 'vector_mvt') {
                return new ol.layer.VectorTile({
                    ...common,
                    source: new ol.source.VectorTile({ format: new ol.format.MVT(), url: definition.tile_url, cacheSize: 512 }),
                    style: function(feature) {
                        const geometryType = feature.getGeometry()?.getType();
                        if (geometryType === 'Polygon' || geometryType === 'MultiPolygon') {
                            return new ol.style.Style({ fill: new ol.style.Fill({ color: 'rgba(222, 231, 235, .82)' }), stroke: new ol.style.Stroke({ color: '#9aaab4', width: 1 }) });
                        }
                        if (geometryType === 'LineString' || geometryType === 'MultiLineString') {
                            return new ol.style.Style({ stroke: new ol.style.Stroke({ color: '#9aaab4', width: 1.2 }) });
                        }
                        return new ol.style.Style({ image: new ol.style.Circle({ radius: 2, fill: new ol.style.Fill({ color: '#7d8d96' }) }) });
                    }
                });
            }
            return new ol.layer.Tile({
                ...common,
                source: new ol.source.XYZ({ url: definition.tile_url, attributions: definition.attribution || undefined, maxZoom: definition.max_zoom })
            });
        }

        const baseMapLayer = createBaseMapLayer(baseMapDefinition);



        map = new ol.Map({
            target: 'map',
            layers: [
                baseMapLayer,
                ...(operationalLayer ? [operationalLayer] : [])
            ],
            view: new ol.View({
                center: ol.proj.fromLonLat([49.5, 34.0]),
                zoom: Math.min(baseMapDefinition.max_zoom, Math.max(baseMapDefinition.min_zoom, 10)),
                minZoom: baseMapDefinition.min_zoom,
                maxZoom: baseMapDefinition.max_zoom
            })
        });

        @if($isLocal)
        document.getElementById('btnDemoRisers').addEventListener('click', async function() {
            try {
                const response = await fetch(@json(route('myextent', ['demo' => 1])), { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error(`Extent HTTP ${response.status}`);
                const data = await response.json();
                if (!data.extent) { document.getElementById('demoRiserStatus').textContent = 'علمک آزمایشی یافت نشد'; return; }
                const extent = ol.proj.transformExtent(data.extent, 'EPSG:4326', map.getView().getProjection());
                document.getElementById('demoRiserStatus').textContent = 'محدوده علمک‌های آزمایشی';
                map.getView().fit(extent, { padding: [100, 100, 100, 100], duration: 700, maxZoom: 18 });
            } catch (error) {
                document.getElementById('demoRiserStatus').textContent = 'دریافت محدوده ناموفق بود';
                console.error('Demo riser extent failed', error);
            }
        });
        @endif

        const riserShowRoute = @json(route('riser.show', ['id' => '__ID__']));
        const riserDetailsDataRoute = @json(route('riser.details', ['id' => '__ID__']));
        const canViewRisers = @json($canViewRisers);
        @if($canFieldReport)
        const maintenanceCaseStoreUrl = @json(route('maintenance-cases.store'));
        const maintenanceCaseBaseUrl = @json(url('/maintenance-cases'));
        let selectedCaseId = null;
        function openMaintenanceModal(id, code) {
            selectedCaseId = null;
            document.getElementById('maintenanceCaseForm').reset();
            document.getElementById('maintenanceOperationForm').reset();
            document.getElementById('maintenanceRiserId').value = id;
            document.getElementById('maintenanceRiserLabel').textContent = `علمک ${code} (#${id})`;
            document.getElementById('maintenanceMessage').textContent = '';
            document.getElementById('maintenanceCaseForm').classList.remove('d-none');
            document.getElementById('maintenanceOperationForm').classList.add('d-none');
            const now = new Date();
            document.querySelector('#maintenanceCaseForm [name="identified_at"]').value = new Date(now.getTime() - now.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            document.querySelector('#maintenanceOperationForm [name="operation_date"]').value = now.toISOString().slice(0, 10);
            document.getElementById('maintenanceModal').classList.add('is-open');
        }
        function openExistingMaintenanceCase(caseId) {
            if (!caseId) return;
            selectedCaseId = caseId;
            document.getElementById('maintenanceCaseForm').classList.add('d-none');
            document.getElementById('maintenanceOperationForm').classList.remove('d-none');
            document.getElementById('maintenanceMessage').textContent = `پرونده ${caseId} انتخاب شد؛ بعد از انجام تعمیر گزارش را ثبت کنید.`;
            document.getElementById('maintenanceModal').classList.add('is-open');
        }
        function closeMaintenanceModal() { document.getElementById('maintenanceModal').classList.remove('is-open'); }
        document.querySelectorAll('#maintenanceModal input[type="file"]').forEach(input => input.addEventListener('change', () => {
            const preview = document.querySelector(`[data-preview-for="${input.name}"]`);
            if (!preview) return;
            preview.replaceChildren(...Array.from(input.files || []).map(file => {
                const image = document.createElement('img'); image.alt = 'پیش‌نمایش تصویر'; image.src = URL.createObjectURL(file); return image;
            }));
        }));
        document.getElementById('maintenanceCaseForm').addEventListener('submit', async event => {
            event.preventDefault();
            const message = document.getElementById('maintenanceMessage');
            message.textContent = 'در حال ثبت پرونده…';
            try {
                const response = await fetch(maintenanceCaseStoreUrl, { method: 'POST', body: new FormData(event.currentTarget), headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || `HTTP ${response.status}`);
                selectedCaseId = payload.case.id;
                event.currentTarget.classList.add('d-none');
                const operationForm = document.getElementById('maintenanceOperationForm');
                operationForm.classList.remove('d-none');
                const caseType = payload.case.operation_category_id;
                if (caseType) operationForm.querySelector('[name="operation_category_id"]').value = caseType;
                message.textContent = `پرونده ${selectedCaseId} ثبت شد. پس از تعمیر، فرم گزارش را تکمیل کنید.`;
                if (operationalLayer) operationalLayer.getSource().refresh();
            } catch (error) { message.textContent = error.message; }
        });
        document.getElementById('maintenanceOperationForm').addEventListener('submit', async event => {
            event.preventDefault();
            if (!selectedCaseId) return;
            const form = event.currentTarget;
            const formData = new FormData(form);
            form.querySelectorAll('[data-material-id]').forEach(input => { if (Number(input.value) > 0) formData.append(`materials[${input.dataset.materialId}]`, input.value); });
            const message = document.getElementById('maintenanceMessage');
            message.textContent = 'در حال ارسال گزارش به رئیس ناحیه…';
            try {
                const response = await fetch(`${maintenanceCaseBaseUrl}/${selectedCaseId}/operation`, { method: 'POST', body: formData, headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || `HTTP ${response.status}`);
                message.textContent = 'گزارش تعمیر برای بررسی رئیس ناحیه ارسال شد.';
                if (operationalLayer) operationalLayer.getSource().refresh();
                setTimeout(closeMaintenanceModal, 1200);
            } catch (error) { message.textContent = error.message; }
        });
        @endif
        /* CLICK */
        map.on('click', function(evt) {

            const feature = map.forEachFeatureAtPixel(evt.pixel, f => f);

            const statusLabels = {
                pending: 'در انتظار',
                success: 'ثبت تعمیرات',
                error: 'خطا',
                default: 'پیش‌فرض',
                flagRiser: '🚩 علامت‌گذاری شده',
                submitOperation: '📤 ثبت عمیرات ',
                resolveLead: '⏳ در انتظار نشت یابی',
                approved: '✅ تایید شده',
                rejected: '❌ رد شده'
            };

            if (feature) {
                const selected = feature;
                const status = selected.get('status');
                const id = selected.get('id') || selected.getId();
                const code = selected.get('code') || selected.get('r_giscode') || selected.get('giscode') || '—';
                const info = document.getElementById('info');
                info.replaceChildren();
                const addLine = (label, value) => {
                    const line = document.createElement('div');
                    const strong = document.createElement('b');
                    strong.textContent = `${label}: `;
                    line.append(strong, document.createTextNode(value == null ? '—' : String(value)));
                    info.append(line);
                    return line;
                };
                addLine('GISCode', code);
                addLine('شناسه علمک', id);
                addLine('وضعیت', statusLabels[status] || status || '—');
                const zoneLine = addLine('Zone', 'در حال دریافت…');
                const activeCaseCount = Number(selected.get('active_case_count') || 0);
                addLine('پرونده‌های باز تعمیر', activeCaseCount);
                if (selected.get('case_status')) addLine('وضعیت پرونده', selected.get('case_status'));
                @if($canFieldReport)
                if (activeCaseCount > 0 && selected.get('case_id')) {
                    const existingCase = document.createElement('button');
                    existingCase.type = 'button'; existingCase.className = 'btn btn-outline-primary btn-sm w-100 mt-2';
                    existingCase.textContent = 'ثبت گزارش تعمیر پرونده باز';
                    existingCase.addEventListener('click', () => openExistingMaintenanceCase(selected.get('case_id')));
                    info.append(existingCase);
                }
                const markCase = document.createElement('button');
                markCase.type = 'button'; markCase.className = 'btn btn-warning btn-sm w-100 mt-2';
                markCase.textContent = 'علامت‌گذاری نیاز به تعمیر';
                markCase.addEventListener('click', () => openMaintenanceModal(id, code));
                info.append(markCase);
                @endif
                if (id && canViewRisers) {
                    const details = document.createElement('a');
                    details.className = 'btn btn-primary btn-sm w-100 mt-2';
                    details.href = riserShowRoute.replace('__ID__', encodeURIComponent(id));
                    details.textContent = 'مشاهده جزئیات علمک';
                    info.append(details);
                    const history = document.createElement('a');
                    history.className = 'btn btn-outline-secondary btn-sm w-100 mt-2';
                    history.href = `/api/risers/${encodeURIComponent(id)}/history`;
                    history.target = '_blank';
                    history.rel = 'noopener';
                    history.textContent = 'تاریخچه تعمیرات';
                    info.append(history);

                    fetch(riserDetailsDataRoute.replace('__ID__', encodeURIComponent(id)), { headers: { 'Accept': 'application/json' } })
                        .then(response => { if (!response.ok) throw new Error(`Details HTTP ${response.status}`); return response.json(); })
                        .then(data => {
                            if (info.contains(zoneLine)) zoneLine.lastChild.textContent = data.zone_name || '—';
                        })
                        .catch(error => {
                            if (info.contains(zoneLine)) zoneLine.lastChild.textContent = 'در دسترس نیست';
                            console.error('Riser details request failed', error);
                        });
                } else if (!canViewRisers) {
                    zoneLine.lastChild.textContent = 'نیازمند مجوز مشاهده علمک';
                }
            }
        });

        function openRiserDetails(id) {
            if (!id) {
                alert('شناسه علمک پیدا نشد.');
                return;
            }

            window.location.href = riserShowRoute.replace('__ID__', id);
        }

        async function bookmarkRiser(id) {
            const riserBookmarkRoute = "{{ route('bookmark', '__ID__') }}";

            if (!id) {
                alert('شناسه علمک پیدا نشد.');
                return;
            }

            try {
                const response = await fetch(
                    riserBookmarkRoute.replace('__ID__', id), {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    }
                );

                if (response.status==200) {
                    alert('علمک با موفقیت نشان شد.')

                    // در صورت نیاز متن وضعیت یا دکمه را هم آپدیت کن
                    // location.reload();
                } else {
                    alert('خطا در ثبت');
                }

            } catch (e) {
                console.error(e);
                alert('خطا در ارتباط با سرور');
            }
        }


        /* SEARCH */
        function applyFilter() {

            const code = document.getElementById('searchBox').value.trim();
            if (!code) return;

            const key = currentLayer + '_' + code;
            const feature = featureIndex[key];

            if (!feature) {
                alert('در این لایه پیدا نشد');
                return;
            }

            const coordinate = ol.extent.getCenter(feature.getGeometry().getExtent());

            map.getView().animate({
                center: coordinate,
                zoom: 20,
                duration: 1000
            });

            document.getElementById('info').innerHTML = `
        <b>کد:</b> ${feature.get('code')}<br>
        <b>وضعیت:</b> ${feature.get('status')}
    `;
        }

        /* STATUS FILTER */
        function applyStatusFilter() {

            if (configuredLayers[currentLayer]?.type !== 'vector_tile' || !operationalLayer) {
                return;
            }

            const status = document.getElementById('statusFilter').value;

            operationalLayer.setStyle(function(feature) {

                const featureStatus = feature.get('status');

                if (status !== 'all' && featureStatus !== status) {
                    return null;
                }

                return getStyle(
                    featureStatus,
                    feature.get('code')
                );
            });

            operationalLayer.changed();
        }

        function applyMaintenanceFilters() {
            if (!operationalLayer || currentLayer !== 'riser' || configuredLayers[currentLayer]?.type !== 'vector_tile') return;
            const params = new URLSearchParams();
            const values = {
                case_status: document.getElementById('maintenanceStatusFilter').value,
                supervisor_id: document.getElementById('maintenanceSupervisorFilter').value,
                zone_id: document.getElementById('maintenanceZoneFilter').value,
                operation_category_id: document.getElementById('maintenanceTypeFilter').value
            };
            Object.entries(values).forEach(([key, value]) => { if (value) params.set(key, value); });
            const endpoint = configuredLayers[currentLayer].endpoint.split('?')[0];
            operationalLayer.getSource().setUrl(params.toString() ? `${endpoint}?${params.toString()}` : endpoint);
            operationalLayer.getSource().refresh();
        }

        /* SWITCH LAYER */
        function switchLayer() {

            currentLayer = document.getElementById('layerSwitcher').value;

            // reset index
            Object.keys(featureIndex).forEach(k => delete featureIndex[k]);

            if (operationalLayer) {
                map.removeLayer(operationalLayer);
            }

            operationalLayer = createOperationalLayer(currentLayer);
            if (operationalLayer) {
                map.addLayer(operationalLayer);
            }
        }

        fetch('{{ route('myextent') }}')
            .then(response => {
                if (!response.ok) {
                    throw new Error('زون کاربر دریافت نشد.');
                }

                return response.json();
            })
            .then(data => {
                // superadmin آزاد است و نقشه جابه‌جا نمی‌شود
                if (data.is_superadmin) {
                    return;
                }

                if (!data.geometry) {
                    return;
                }

                const zoneFeature = new ol.format.GeoJSON().readFeature({
                    type: 'Feature',
                    geometry: data.geometry,
                    properties: {
                        name: data.zone_name
                    }
                }, {
                    dataProjection: 'EPSG:4326',
                    featureProjection: 'EPSG:3857'
                });

                // نمایش کادر زون روی نقشه
                zoneLayer = new ol.layer.Vector({
                    source: new ol.source.Vector({
                        features: [zoneFeature]
                    }),
                    style: new ol.style.Style({
                        stroke: new ol.style.Stroke({
                            color: '#3E63DD',
                            width: 3
                        }),
                        fill: new ol.style.Fill({
                            color: 'rgba(62, 99, 221, 0.08)'
                        })
                    })
                });

                map.addLayer(zoneLayer);

                // زوم نقشه روی محدوده زون کاربر
                map.getView().fit(zoneFeature.getGeometry().getExtent(), {
                    padding: [70, 70, 70, 70],
                    duration: 700,
                    maxZoom: 14
                });
            })
            .catch(error => {
                console.error(error);
            });

        document.getElementById('btnHideZone').addEventListener('click', function() {
            if (!zoneLayer) {
                return;
            }

            map.removeLayer(zoneLayer);
            zoneLayer = null;


        });
    </script>

@endsection
