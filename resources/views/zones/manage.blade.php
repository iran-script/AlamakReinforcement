@extends('layouts.app')

@section('title', 'مدیریت زون‌ها')

@section('content')

<meta name="csrf-token" content="{{ csrf_token() }}">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ol@v9.2.4/ol.css">
<script src="https://cdn.jsdelivr.net/npm/ol@v9.2.4/dist/ol.js"></script>

<style>
    .zone-page {
        display: flex;
        gap: 15px;
        height: calc(100vh - 150px);
        min-height: 650px;
    }

    .zone-sidebar {
        width: 320px;
        background: #fff;
        border-radius: 12px;
        padding: 18px;
        overflow-y: auto;
        box-shadow: 0 2px 10px rgba(0,0,0,.08);
    }

    .zone-map-container {
        flex: 1;
        position: relative;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0,0,0,.08);
    }

    #map {
        width: 100%;
        height: 100%;
    }

    .zone-item {
        border: 1px solid #e5e5e5;
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 8px;
        cursor: pointer;
        transition: .2s;
    }

    .zone-item:hover,
    .zone-item.active {
        background: #eef6ff;
        border-color: #0d6efd;
    }

    .map-tools {
        position: absolute;
        top: 12px;
        left: 12px;
        z-index: 1000;
        background: white;
        border-radius: 10px;
        padding: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,.15);
    }

    .map-tools button {
        margin: 3px;
    }

    .form-label {
        font-weight: bold;
    }
</style>

<div class="zone-page">

    <div class="zone-sidebar">

        <h4 class="mb-4">مدیریت زون‌ها</h4>

        <div class="mb-3">
            <label class="form-label">نام زون</label>
            <input type="text" id="zoneName" class="form-control" placeholder="مثلاً زون شمالی">
        </div>

        <input type="hidden" id="selectedZoneId">

        <div class="d-grid gap-2 mb-4">
            <button class="btn btn-primary" id="saveZoneBtn">
                ذخیره زون
            </button>

            <button class="btn btn-warning" id="editZoneBtn" disabled>
                ویرایش هندسه
            </button>

            <button class="btn btn-danger" id="deleteZoneBtn" disabled>
                حذف زون
            </button>

            <button class="btn btn-secondary" id="cancelBtn">
                لغو انتخاب
            </button>
        </div>

        <hr>

        <h6 class="mb-3">زون‌های ثبت‌شده</h6>

        <div id="zonesList">
            <div class="text-muted">در حال دریافت زون‌ها...</div>
        </div>

    </div>

    <div class="zone-map-container">

        <div class="map-tools">
            <button class="btn btn-success btn-sm" id="drawPolygonBtn">
                ترسیم زون جدید
            </button>

            <button class="btn btn-outline-danger btn-sm" id="clearDrawBtn">
                پاک کردن ترسیم
            </button>
        </div>

        <div id="map"></div>

    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    const zoneRoutes = {
        index: @json(route('zones.index')),
        store: @json(route('zones.store')),
        update: @json(route('zones.update', ['zone' => '__ZONE_ID__'])),
        destroy: @json(route('zones.destroy', ['zone' => '__ZONE_ID__'])),
    };

    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    let selectedFeature = null;
    let drawInteraction = null;
    let modifyInteraction = null;

    const zoneSource = new ol.source.Vector();

    const zoneLayer = new ol.layer.Vector({
        source: zoneSource,
        style: function(feature) {
            const isSelected = feature === selectedFeature;

            return new ol.style.Style({
                fill: new ol.style.Fill({
                    color: isSelected
                        ? 'rgba(13, 110, 253, 0.35)'
                        : 'rgba(25, 135, 84, 0.25)'
                }),
                stroke: new ol.style.Stroke({
                    color: isSelected ? '#0d6efd' : '#198754',
                    width: isSelected ? 3 : 2
                }),
                text: new ol.style.Text({
                    text: feature.get('name') || '',
                    font: 'bold 13px sans-serif',
                    fill: new ol.style.Fill({
                        color: '#222'
                    }),
                    stroke: new ol.style.Stroke({
                        color: '#fff',
                        width: 3
                    })
                })
            });
        }
    });

    const map = new ol.Map({
        target: 'map',
        layers: [
            new ol.layer.Tile({
                source: new ol.source.OSM()
            }),
            zoneLayer
        ],
        view: new ol.View({
            center: ol.proj.fromLonLat([51.3890, 35.6892]),
            zoom: 11
        })
    });

    function loadZones() {
        fetch(zoneRoutes.index, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async response => {
            if (!response.ok) {
                throw new Error('خطا در دریافت زون‌ها');
            }

            return response.json();
        })
        .then(data => {
            zoneSource.clear();

            const list = document.getElementById('zonesList');
            const format = new ol.format.GeoJSON();

            const validFeatures = (data.features || [])
                .filter(item => item.geometry && item.geometry.type && item.geometry.coordinates)
                .map(item => {
                    try {
                        const feature = format.readFeature(item, {
                            featureProjection: 'EPSG:3857',
                            dataProjection: 'EPSG:4326'
                        });

                        feature.set('id', item.properties?.id);
                        feature.set('name', item.properties?.name || 'بدون نام');

                        return feature;
                    } catch (error) {
                        console.warn('هندسه نامعتبر برای زون:', item, error);
                        return null;
                    }
                })
                .filter(feature => feature && feature.getGeometry());

            zoneSource.addFeatures(validFeatures);

            renderZonesList(validFeatures);

            if (validFeatures.length > 0) {
                const extent = zoneSource.getExtent();

                if (extent && !ol.extent.isEmpty(extent)) {
                    map.getView().fit(extent, {
                        padding: [60, 60, 60, 60],
                        maxZoom: 16,
                        duration: 700
                    });
                }
            } else {
                list.innerHTML = '<div class="text-muted">هنوز زونی ثبت نشده است.</div>';
            }
        })
        .catch(error => {
            console.error(error);
            document.getElementById('zonesList').innerHTML =
                '<div class="text-danger">خطا در دریافت زون‌ها.</div>';
        });
    }
    function renderZonesList(features) {
        const list = document.getElementById('zonesList');

        if (!features.length) {
            list.innerHTML = '<div class="text-muted">هنوز زونی ثبت نشده است.</div>';
            return;
        }

        list.innerHTML = '';

        features.forEach(feature => {
            const zoneId = feature.get('id');
            const zoneName = feature.get('name') || 'بدون نام';

            const item = document.createElement('div');

            item.className = 'zone-item';
            item.dataset.id = zoneId;

            item.innerHTML = `
                <strong>${zoneName}</strong>
                <br>
                <small class="text-muted">شناسه: ${zoneId}</small>
            `;

            item.addEventListener('click', function () {
                selectZone(feature);
            });

            list.appendChild(item);
        });
    }

    function selectZone(feature) {
        if (!feature || !feature.getGeometry()) {
            alert('هندسه این زون معتبر نیست.');
            return;
        }
        
        selectedFeature = feature;

        const zoneId = feature.get('id');
        const zoneName = feature.get('name') || '';

        document.getElementById('selectedZoneId').value = zoneId;
        document.getElementById('zoneName').value = zoneName;

        document.getElementById('editZoneBtn').disabled = false;
        document.getElementById('deleteZoneBtn').disabled = false;

        document.querySelectorAll('.zone-item').forEach(item => {
            item.classList.toggle(
                'active',
                String(item.dataset.id) === String(zoneId)
            );
        });

        const extent = feature.getGeometry().getExtent();
        console.log(extent);

        if (extent) {
            map.getView().fit(extent, {
                padding: [100, 380, 100, 100],
                maxZoom: 18,
                duration: 700
            });
        }

        zoneLayer.changed();
    }

    function clearSelection() {
        selectedFeature = null;

        document.getElementById('selectedZoneId').value = '';
        document.getElementById('zoneName').value = '';

        document.getElementById('editZoneBtn').disabled = true;
        document.getElementById('deleteZoneBtn').disabled = true;

        document.querySelectorAll('.zone-item').forEach(item => {
            item.classList.remove('active');
        });

        removeModifyInteraction();
        zoneLayer.changed();
    }

    function startDraw() {
        clearSelection();
        removeDrawInteraction();
        removeModifyInteraction();

        zoneId = null;

       

        // اگر Draw قبلی وجود دارد حذف شود
        if (drawInteraction) {
            map.removeInteraction(drawInteraction);
        }

        drawInteraction = new ol.interaction.Draw({
            source: zoneSource,
            type: 'Polygon'
        });

        map.addInteraction(drawInteraction);

        map.addInteraction(drawInteraction);

        drawInteraction.on('drawend', function(event) {
            selectedFeature = event.feature;

            selectedFeature.set('isNew', true);

            document.getElementById('zoneName').focus();

            removeDrawInteraction();

            zoneLayer.changed();
        });
    }

    function removeDrawInteraction() {
        if (drawInteraction) {
            map.removeInteraction(drawInteraction);
            drawInteraction = null;
        }
    }

    function startModifyInteraction() {
        if (!selectedFeature) {
            alert('ابتدا یک زون را انتخاب کن.');
            return;
        }

        removeDrawInteraction();
        removeModifyInteraction();

        modifyInteraction = new ol.interaction.Modify({
            features: new ol.Collection([selectedFeature])
        });

        map.addInteraction(modifyInteraction);

        alert('حالا نقاط زون را با ماوس جابه‌جا کن و سپس ذخیره زون را بزن.');
    }

    function removeModifyInteraction() {
        if (modifyInteraction) {
            map.removeInteraction(modifyInteraction);
            modifyInteraction = null;
        }
    }

    function getFeatureGeometryGeoJSON(feature) {
        const format = new ol.format.GeoJSON();

        return format.writeGeometryObject(feature.getGeometry(), {
            featureProjection: 'EPSG:3857',
            dataProjection: 'EPSG:4326'
        });
    }

    function saveZone() {
        const name = document.getElementById('zoneName').value.trim();
        const zoneId = document.getElementById('selectedZoneId').value;

        if (!name) {
            alert('نام زون را وارد کن.');
            return;
        }

        if (!selectedFeature) {
            alert('ابتدا یک زون ترسیم یا انتخاب کن.');
            return;
        }

        const geometry = getFeatureGeometryGeoJSON(selectedFeature);

        const hasValidZoneId =
        zoneId !== undefined &&
        zoneId !== null &&
        zoneId !== '' &&
        zoneId !== 'undefined' &&
        zoneId !== 'null' &&
        Number.isInteger(Number(zoneId)) &&
        Number(zoneId) > 0;

        const url = hasValidZoneId
        ? zoneRoutes.update.replace('__ZONE_ID__', zoneId)
        : zoneRoutes.store;
        console.log(zoneId);

    const method = zoneId ? 'PUT' : 'POST';

        fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                name: name,
                geometry: geometry
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);

                clearSelection();
                loadZones();
            } else {
                alert('خطا در ذخیره زون');
            }
        })
        .catch(error => {
            console.error(error);
            alert('خطا در ارتباط با سرور');
        });
    }

    function deleteZone() {
        const zoneId = document.getElementById('selectedZoneId').value;

        if (!zoneId) {
            alert('ابتدا یک زون را انتخاب کن.');
            return;
        }

        const url = zoneRoutes.destroy.replace('__ZONE_ID__', zoneId);

        if (!confirm('آیا از حذف این زون مطمئن هستی؟')) {
            return;
        }

        fetch(url, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(async response => {
            if (!response.ok) {
                throw new Error('خطا در حذف زون');
            }

            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(data.message);
                clearSelection();
                loadZones();
            }
        })
        .catch(error => {
            console.error(error);
            alert('خطا در حذف زون');
        });
    }

    map.on('singleclick', function (event) {
        let clickedFeature = null;

        map.forEachFeatureAtPixel(
            event.pixel,
            function (feature, layer) {
                if (layer === zoneLayer) {
                    clickedFeature = feature;
                    return true;
                }
            },
            {
                hitTolerance: 5
            }
        );

        if (clickedFeature) {
            selectZone(clickedFeature);
        }
    });

    document.getElementById('drawPolygonBtn').addEventListener('click', startDraw);

    document.getElementById('clearDrawBtn').addEventListener('click', function () {
        if (selectedFeature && selectedFeature.get('isNew')) {
            zoneSource.removeFeature(selectedFeature);
        }

        clearSelection();
        removeDrawInteraction();
    });

    document.getElementById('saveZoneBtn').addEventListener('click', saveZone);

    document.getElementById('editZoneBtn').addEventListener('click', startModifyInteraction);

    document.getElementById('deleteZoneBtn').addEventListener('click', deleteZone);

    document.getElementById('cancelBtn').addEventListener('click', clearSelection);

    loadZones();

});
</script>

@endsection