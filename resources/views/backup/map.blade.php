@extends('layouts.app')

@section('title', 'نقشه GIS')

@section('content')

    <link href="{{ url('css/ol.css') }}" rel="stylesheet">
    <script src="{{ asset('js/ol.js') }}"></script>


    <style>
        .map-wrapper {
            position: relative;
        }

        #map {
            width: 100%;
            height: 85vh;
            border-radius: 10px;
        }

        /* TOOLBAR */
        .toolbar {
            position: absolute;
            top: 10px;
            left: 20px;
            right: 20px;
            z-index: 1000;

            background: white;
            padding: 10px;
            border-radius: 10px;

            display: flex;
            gap: 10px;
            align-items: center;

            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .toolbar select,
        .toolbar input {
            padding: 6px;
            border-radius: 6px;
            border: 1px solid #ccc;
        }

        /* INFO PANEL */
        .info-panel {
            position: absolute;
            bottom: 20px;
            right: 20px;

            width: 300px;
            background: white;
            padding: 15px;
            border-radius: 10px;

            z-index: 1000;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
        }
    </style>

    <div class="map-wrapper">

        <!-- TOOLBAR -->
        <div class="toolbar">

            وضعیت:
            <select id="statusFilter" onchange="applyStatusFilter()">
                <option value="all">همه</option>
                <option value="pending">تعمیر شده</option>
                <option value="default">تعمیر نشده</option>
                <option value="success"> تایید شده</option>
                <option value="error"> رد شده</option>
            </select>

            <!-- LAYER SWITCHER -->
            {{-- لایه:
        <select id="layerSwitcher" onchange="switchLayer()">
            <option value="riser">Riser</option>
            <option value="valve">Valve</option>
            <option value="pipe">Pipe</option>
        </select> --}}


            <button id="btnHideZone" type="button" class="btn btn-outline-danger" title="حذف  ترسیمات">
                <i class="bi bi-scissors"></i>
            </button>

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
            <h6>اطلاعات عارضه</h6>
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
                pending: '#f1c40f', // زرد
                success: '#2ecc71', // سبز
                error: '#e74c3c' // قرمز
            };

            if (!styleCache[key]) {

                styleCache[key] = new ol.style.Style({

                    image: new ol.style.Circle({
                        radius: 5,
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
                        offsetY: -15,
                        font: '12px Tahoma',
                        fill: new ol.style.Fill({
                            color: '#111'
                        }),
                        stroke: new ol.style.Stroke({
                            color: '#fff',
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

        map = new ol.Map({
            target: 'map',
            layers: [
                new ol.layer.Tile({
                    source: new ol.source.OSM()
                }),
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

            if (feature) {
                document.getElementById('info').innerHTML = `
            <b>کد:</b> ${feature.get('code')}<br>
            <b>وضعیت:</b> ${feature.get('status')}<br><br>

            <button class="btn btn-primary btn-sm"
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
            console.log(status);

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
                            color: '#dc3545',
                            width: 3
                        }),
                        fill: new ol.style.Fill({
                            color: 'rgba(220, 53, 69, 0.10)'
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
