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

            <!-- LAYER SWITCHER -->
            {{-- لایه:
        <select id="layerSwitcher" onchange="switchLayer()">
            <option value="riser">Riser</option>
            <option value="valve">Valve</option>
            <option value="pipe">Pipe</option>
        </select> --}}


            <button id="btnHideZone" type="button" class="btn btn-outline-danger btn-sm" title="حذف  ترسیمات">
                <i class="bi bi-scissors"></i>
                حذف ترسیمات
            </button>

            <div class="legend">
                <span><i class="dot dot-done"></i> تعمیر شده</span>
                <span><i class="dot dot-pending"></i> تعمیر نشده</span>
                <span><i class="dot dot-pending"></i> تعمیر نشده</span>
                <span><i class="dot dot-pending"></i> تعمیر نشده</span>
                <span><i class="dot dot-pending"></i> تعمیر نشده</span>
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

    <script>
        let currentLayer = 'riser';

        const styleCache = {};
        const featureIndex = {};

        function getTileUrl(layer) {
            return `{{ url('/tiles') }}/${layer}/{z}/{x}/{y}.pbf`;
        }

        function getStyle(status, code) {

            const key = status + '_' + (code || '');

            const statusColors = {
                default: '#5c6161',
                flagRiser: '#f1c40f', // زرد
                submitOperation: '#2ecc71', // سبز
                resolveLead: '#e74c3c',
                error: '#e74721'
            };

            if (!styleCache[key]) {

                styleCache[key] = new ol.style.Style({

                    image: new ol.style.Circle({
                        radius: 6,
                        fill: new ol.style.Fill({
                            color: statusColors[status] || '#95a5a6'
                        }),
                        stroke: new ol.style.Stroke({
                            color: '#fff',
                            width: 3
                        })
                    }),

                    text: new ol.style.Text({
                        text: code || '',
                        offsetY: -16,
                        font: '600 12px Vazirmatn, Tahoma',
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

        let vectorTileLayer = new ol.layer.VectorTile({
            declutter: true,
            source: new ol.source.VectorTile({
                format: new ol.format.MVT(),
                url: getTileUrl(currentLayer),
                cacheSize: 512
            }),

            style: function(feature) {

                const code = feature.get('code');

                if (code) {
                    const key = currentLayer + '_' + code;
                    featureIndex[key] = feature;
                }

                return getStyle(
                    feature.get('status'),
                    code
                );
            }
        });

        const osm = new ol.layer.Tile({
            source: new ol.source.XYZ({
                url: '{{ url('/tiles/{z}/{x}/{y}.png') }}'
            })
        });



        map = new ol.Map({
            target: 'map',
            layers: [
                osm,
                vectorTileLayer
            ],
            view: new ol.View({
                center: ol.proj.fromLonLat([49.5, 34.0]),
                zoom: 10
            })
        });

        const riserShowRoute = @json(route('riser.show', ['id' => '__ID__']));
        /* CLICK */
        map.on('click', function(evt) {

            const feature = map.forEachFeatureAtPixel(evt.pixel, f => f);

            const statusLabels = {
                default: 'پیش‌فرض',
                flagRiser: '🚩 علامت‌گذاری شده',
                submitOperation: '📤 ثبت عمیرات ',
                resolveLead: '⏳ در انتظار نشت یابی',
                approved: '✅ تایید شده',
                rejected: '❌ رد شده'
            };

            const status = feature.get('status');



            if (feature) {
                document.getElementById('info').innerHTML = `
                    <b>کد:</b><br>
                    <span class="plate">${feature.get('code')}</span><br><br>

                    <b>وضعیت:</b> ${statusLabels[status] || status}<br><br>

                    ${
                        feature.get('status') === 'default'
                        ? `
                                                    <button class="btn btn-warning btn-sm w-100 mb-2"
                                                            onclick="bookmarkRiser(${feature.get('id')})">
                                                        <i class="bi bi-bookmark"></i>
                                                        افزودن به نشان‌شده‌ها
                                                    </button>
                                                `
                        : ''
                    }

                    <button class="btn btn-primary btn-sm w-100"
                            onclick="openRiserDetails(${feature.get('id')})">
                        <i class="bi bi-eye"></i>
                        مشاهده اطلاعات کامل
                    </button>
                `;
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

            const status = document.getElementById('statusFilter').value;

            vectorTileLayer.setStyle(function(feature) {

                const featureStatus = feature.get('status');

                if (status !== 'all' && featureStatus !== status) {
                    return null;
                }

                return getStyle(
                    featureStatus,
                    feature.get('code')
                );
            });

            vectorTileLayer.changed();
        }

        /* SWITCH LAYER */
        function switchLayer() {

            currentLayer = document.getElementById('layerSwitcher').value;

            // reset index
            Object.keys(featureIndex).forEach(k => delete featureIndex[k]);

            vectorTileLayer.setSource(
                new ol.source.VectorTile({
                    format: new ol.format.MVT(),
                    url: getTileUrl(currentLayer),
                    cacheSize: 512
                })
            );

            vectorTileLayer.changed();
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
