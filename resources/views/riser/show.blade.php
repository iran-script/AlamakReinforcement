@extends('layouts.app')

@section('title', 'جزئیات علمک')

@section('content')

    <link href="{{ url('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ url('css/bootstrap-icon.css') }}" rel="stylesheet">
    <script src="{{ url('js/bootstrap.js') }}"></script>
    <link rel="stylesheet" href="{{ url('css/dropzone.css') }}">
    <link href="{{ url('css/ol.css') }}" rel="stylesheet">
    <script src="{{ asset('js/ol.js') }}"></script>

    <script src="{{ url('js/dropzone.js') }}"></script>
    <script>
        Dropzone.autoDiscover = false;

        // مجبور کردن Dropzone به باز کردن مستقیم دوربین (نه گالری/فایل‌های قدیمی)
        // چون Dropzone هر بار بعد از انتخاب فایل، input مخفی‌اش را از نو می‌سازد،
        // این پچ روی prototype تضمین می‌کند capture همیشه اعمال بماند.
        Dropzone.prototype._originalSetupHiddenFileInput = Dropzone.prototype._setupHiddenFileInput;
        Dropzone.prototype._setupHiddenFileInput = function () {
            this._originalSetupHiddenFileInput();
            this.hiddenFileInput.setAttribute('capture', 'environment');
            this.hiddenFileInput.setAttribute('accept', 'image/*');
        };
    </script>


    <style>
        body {
            background: #f4f6f9;
        }

        .page-header {

            background: #fff;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;

            box-shadow: 0 2px 10px rgba(0, 0, 0, .08);
        }

        .page-title {

            font-size: 28px;
            font-weight: bold;
        }

        .status-badge {

            font-size: 15px;
        }

        .card-custom {

            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .08);
            margin-bottom: 20px;
        }

        .main-image {

            width: 100%;
            height: 420px;

            object-fit: cover;

            border-radius: 12px;

            cursor: pointer;
        }

        .thumb {

            width: 90px;
            height: 90px;

            object-fit: cover;

            border-radius: 8px;

            cursor: pointer;

            border: 3px solid transparent;

            transition: .2s;
        }

        .thumb:hover {

            border-color: #0d6efd;
        }

        .info-item {

            margin-bottom: 15px;
        }

        .info-label {

            font-weight: bold;
            color: #6c757d;
        }

        .table-placeholder {

            height: 250px;

            display: flex;

            align-items: center;

            justify-content: center;

            color: #999;

            font-size: 18px;
        }

        :root {
            --navy-950: #0B2033;
            --navy-900: #0F2A43;
            --navy-800: #16405C;
            --blueprint: #2B5872;
            --blueprint-soft: #3E6C86;
            --paper: #EEF0EA;
            --paper-dim: #E3E6DD;
            --ink: #132433;
            --muted: #5C7488;
            --amber: #D98F2B;
            --amber-dark: #B8721B;
            --green: #3F7A5C;
            --green-soft: #E4EFE8;
            --radius: 10px;
            --maxw: 1180px;
        }

        .showcase {
            background: var(--navy-950);
            color: #fff;
        }

        .showcase .section-head {
            max-width: 640px;
        }

        .showcase .eyebrow {
            color: var(--amber);
        }

        .showcase .section-head h2 {
            color: #fff;
        }

        .showcase .section-head p {
            color: #AEBCC7;
        }

        .showcase {
            position: relative;
        }

        .showcase-glow {
            position: absolute;
            left: 50%;
            top: 40%;
            width: 640px;
            height: 640px;
            background: radial-gradient(circle, rgba(217, 143, 43, .16), transparent 68%);
            transform: translate(-50%, -50%);
            pointer-events: none;
            filter: blur(10px);
        }

        .report-card {
            position: relative;
            background: #fff;
            color: var(--ink);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 30px 70px rgba(0, 0, 0, .4), 0 0 0 1px rgba(255, 255, 255, .06);
            transition: transform .4s ease;
        }

        .report-card:hover {
            transform: translateY(-4px);
        }

        .report-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            border-bottom: 1px dashed #D7DBD1;
            flex-wrap: wrap;
            gap: 10px;
        }

        .report-meta .m-item {
            font-size: 12.5px;
            color: var(--muted);
        }

        .report-meta .m-item b {
            display: block;
            color: var(--ink);
            font-size: 14px;
            font-family: 'JetBrains Mono', monospace;
            direction: ltr;
            text-align: right;
        }

        .compare {
            position: relative;
            width: 100%;
            aspect-ratio: 16/8;
            overflow: hidden;
            user-select: none;
        }

        .compare .layer {
            position: absolute;
            inset: 0;
        }

        .compare .label {
            position: absolute;
            top: 14px;
            font-size: 12px;
            font-weight: 700;
            background: rgba(11, 32, 51, .75);
            color: #fff;
            padding: 4px 12px;
            border-radius: 20px;
            font-family: 'JetBrains Mono', monospace;
            letter-spacing: .05em;
        }

        .compare .label.before {
            right: 14px;
        }

        .compare .label.after {
            left: 14px;
        }

        .compare .after-layer {
            clip-path: inset(0 50% 0 0);
        }

        .compare .divider {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 6px;
            background: var(--amber);
            right: 50%;
            transform: translateX(50%);
            z-index: 5;
            pointer-events: none;
        }

        .compare .handle {
            position: absolute;
            top: 50%;
            right: 50%;
            transform: translate(50%, -50%);
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: var(--amber);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 6;
            box-shadow: 0 6px 16px rgba(0, 0, 0, .3);
        }

        .compare input[type=range] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            margin: 0;
            opacity: 0;
            cursor: ew-resize;
            z-index: 7;
        }

        .report-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            background: #F7F8F4;
        }

        .report-footer .verdict {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            color: var(--green);
            font-size: 14.5px;
        }

        .report-footer .verdict::before {
            content: "✓";
            display: flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: var(--green);
            color: #fff;
            font-size: 12px;
        }

        .report-footer .note {
            font-size: 13px;
            color: var(--muted);
        }
    </style>



    <div class="container-fluid py-4">

        <!-- HEADER -->

        <div class="page-header">

            <div class="row align-items-center">

                <div class="col-lg-8">

                    <h2 class="page-title mb-3">

                        <i class="bi bi-geo-alt-fill text-danger"></i>

                        علمک شماره

                        <span id="riserCode">
                            ---
                        </span>

                    </h2>

                    <div class="d-flex flex-wrap gap-3">

                        <span class="badge bg-success status-badge" id="riserStatus">

                            تعمیر شده

                        </span>

                        <span>

                            <i class="bi bi-calendar-event"></i>

                            آخرین بروزرسانی:

                            <span id="updatedAt">

                                ---

                            </span>

                        </span>

                    </div>

                </div>

                <div class="col-lg-4 text-end">

                    <a href="{{ url()->previous() }}" class="btn btn-secondary">

                        <i class="bi bi-arrow-right"></i>

                        بازگشت به نقشه

                    </a>

                    <button class="btn btn-primary">

                        <i class="bi bi-pencil"></i>

                        ویرایش

                    </button>

                </div>

            </div>

        </div>



        <div class="row">

            <!-- LEFT -->

            <div class="col-lg-8">
                <!-- GALLERY -->

                <div class="card card-custom">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-images"></i>

                            تصاویر علمک

                        </h5>

                    </div>

                    <div class="card-body">

                        <img id="mainImage" src="{{ url('img/alamak.jpg') }}" class="main-image">

                        <div class="d-flex gap-2 mt-3 flex-wrap" id="thumbnailContainer">

                            <img src="{{ url('img/alamak.jpg') }}" class="thumb">

                            <img src="{{ url('img/alamak.jpg') }}" class="thumb">

                            <img src="{{ url('img/alamak.jpg') }}" class="thumb">

                        </div>

                    </div>

                </div>



                <!-- OPERATIONS -->

                <div class="card card-custom">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-tools"></i>

                            عملیات انجام شده

                        </h5>

                    </div>

                    <div class="card-body">

                        <div class="d-flex justify-content-end mb-3 gap-2">
                            <button class="btn btn-outline-primary" onclick="beforePhotoModal.show()">
                                <i class="bi bi-camera"></i>
                                ثبت عکس قبل از عملیات
                            </button>
                            <button class="btn btn-success" onclick="openCreateOperation()">
                                <i class="bi bi-plus-circle"></i>
                                عملیات جدید
                            </button>
                        </div>

                        <div class="table-responsive">

                            <table class="table table-bordered table-hover align-middle" id="operationTable">

                                <thead class="table-light">

                                    <tr>

                                        <th>#</th>

                                        <th>تاریخ</th>

                                        <th>نوع عملیات</th>

                                        <th>پیمانکار</th>

                                        <th>وضعیت</th>

                                        <th>اقلام مصرفی</th>

                                        <th>جزئیات</th>

                                    </tr>

                                </thead>

                                <tbody>

                                </tbody>

                            </table>
                        </div>

                    </div>

                </div>



                <!-- SUPERVISORS -->

                <div class="card card-custom">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-person-check-fill"></i>

                            تاییدکنندگان عملیات

                        </h5>

                    </div>

                    <div class="card-body">

                        <table class="table table-bordered table-striped" id="supervisorTable">

                            <thead class="table-light">

                                <tr>

                                    <th>#</th>

                                    <th>عملیات</th>

                                    <th>ناظر</th>

                                    <th>سمت</th>

                                    <th>تاریخ تایید</th>

                                    <th>نتیجه</th>

                                </tr>

                            </thead>

                            <tbody>

                            </tbody>

                        </table>

                    </div>

                </div>

                <!-- WORKFLOW -->

                <div class="card card-custom">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-person-check-fill"></i>

                            گردش کار
                        </h5>

                    </div>

                    <div class="card-body">

                        <table class="table table-bordered table-striped" id="workflowTable">

                            <thead class="table-light">

                                <tr>

                                    <th>#</th>

                                    <th>موضوع</th>

                                    <th>فرستنده</th>

                                    <th>گیرنده</th>

                                    <th>تاریخ ارسال</th>

                                    <th>نتیجه</th>

                                </tr>

                            </thead>

                            <tbody>

                            </tbody>

                        </table>

                    </div>

                </div>



            </div>



            <!-- RIGHT -->

            <div class="col-lg-4">

                <div class="card card-custom">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-info-circle-fill"></i>

                            مشخصات علمک

                        </h5>

                    </div>

                    <div class="card-body">

                        <div class="info-item">

                            <div class="info-label">

                                کد علمک

                            </div>

                            <div id="infoCode">

                                -

                            </div>

                        </div>



                    </div>

                </div>



                <div class="card card-custom">

                    <div class="card-header bg-white">

                        <h5>

                            <i class="bi bi-geo-alt"></i>

                            موقعیت

                        </h5>

                    </div>

                    <div class="card-body p-0">

                        <div id="miniMap" style="height:320px;background:#ddd;border-radius:0 0 15px 15px;">

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- Create Operation Modal -->
    <div class="modal fade" id="createOperationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">

            <form id="createOperationForm" action="{{ route('operations.store') }}" method="POST">
                @csrf
                <input type="hidden" name="riser_id" id="operation_riser_id">
                <input type="hidden" id="operation_edit_id" value="">

                <div class="modal-content">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                    <div class="modal-header">
                        <h5 class="modal-title" id="operationModalTitle">
                            ثبت عملیات جدید
                        </h5>

                    </div>

                    <div class="modal-body">

                        <div class="row g-3">

                            <div class="col-md-6">
                                <label class="form-label">نوع عملیات</label>
                                <select class="form-select" id="operationcat" name="operationcat" required>
                                    <option value="">در حال بارگذاری...</option>
                                </select>
                            </div>


                            <div class="col-md-6">
                                <label class="form-label">وضعیت</label>

                                <select class="form-select" name="status">
                                    <option value="درحال انجام">درحال انجام</option>
                                    <option value="انجام شده">انجام شده</option>
                                    <option value="متوقف شده">اتمام</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">اولویت</label>

                                <select class="form-select" name="priority">
                                    <option value="کم">کم</option>
                                    <option value="متوسط">متوسط</option>
                                    <option value="زیاد">زیاد</option>
                                    <option value="فوری">فوری</option>
                                </select>
                            </div>

                            <div class="col-12">

                                <label class="form-label">
                                    متریال مصرفی
                                </label>

                                <input
                                    type="text"
                                    id="materialSearch"
                                    class="form-control mb-2"
                                    placeholder="جستجوی متریال..."
                                    autocomplete="off">

                                <div style="max-height: 320px; overflow-y: auto;">
                                    <table class="table table-bordered table-hover align-middle mb-0">
                                        <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                            <tr>
                                                <th>متریال</th>
                                                <th width="120">واحد</th>
                                                <th width="150">تعداد</th>
                                            </tr>
                                        </thead>

                                        <tbody id="materialsContainer">

                                        </tbody>

                                    </table>
                                </div>

                                <div id="materialSearchEmpty" class="text-muted small mt-2 d-none">
                                    موردی یافت نشد.
                                </div>

                            </div>

                            <div class="col-12">
                                <label class="form-label">
                                    <i class="bi bi-camera"></i>
                                    عکس‌های قبل از عملیات (ثبت‌شده)
                                </label>
                                <div id="pendingBeforePhotos" class="d-flex flex-wrap gap-2 mb-1"></div>
                                <div class="form-text">
                                    این عکس‌ها خودکار به این عملیات وصل میشن. اگه هنوز نگرفتی، از دکمه «ثبت عکس قبل از
                                    عملیات» بالای جدول استفاده کن.
                                </div>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">توضیحات</label>

                                <textarea class="form-control" rows="4" name="description"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">
                                    <i class="bi bi-camera-fill"></i>
                                    عکس‌های بعد از تعمیر
                                </label>
                                <div id="operationDropzoneAfter" class="dropzone"></div>
                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            انصراف
                        </button>

                        <button type="submit" class="btn btn-primary" id="operationSubmitBtn">
                            <i class="bi bi-save"></i>
                            ذخیره عملیات
                        </button>

                    </div>

                </div>

            </form>

        </div>
    </div>



    <div class="modal fade" id="beforePhotoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">ثبت عکس قبل از عملیات</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-2">
                        این عکس‌ها همین الان ذخیره میشن و خودکار به اولین عملیاتی که برای این علمک ثبت کنی وصل میشن.
                    </p>
                    <div id="beforePhotoDropzone" class="dropzone"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const beforePhotoModal = new bootstrap.Modal(document.getElementById('beforePhotoModal'));

        const dzStandaloneBefore = new Dropzone("#beforePhotoDropzone", {
            url: "{{ url('riser/photos/upload') }}",
            paramName: "photo",
            maxFilesize: 10,
            acceptedFiles: "image/*",
            autoProcessQueue: true,
            headers: {
                'X-CSRF-TOKEN': "{{ csrf_token() }}"
            },
        });

        dzStandaloneBefore.on("sending", function(file, xhr, formData) {
            formData.append('riser_id', riserData.id);
            formData.append('type', 'before');
        });
    </script>

    <script>
        document.getElementById("createOperationForm")
            .addEventListener("submit", async function(e) {

                e.preventDefault();

                const form = this;
                const formData = new FormData(form);
                const editId = document.getElementById("operation_edit_id").value;

                // فایل‌های عکس بعد از تعمیر (هم در ثبت، هم در ویرایش قابل اضافه‌شدن است)
                dzAfter.getAcceptedFiles().forEach(file => {
                    formData.append('photos_after[]', file);
                });

                let url = form.action;

                if (editId) {
                    // Laravel method spoofing: خودِ درخواست POST می‌ماند تا آپلود فایل درست کار کند،
                    // ولی روتینگ آن را به‌عنوان PUT در نظر می‌گیرد
                    formData.append('_method', 'PUT');
                    url = `{{ url('operations') }}/${editId}`;
                }

                try {

                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                            'Accept': 'application/json'
                        },
                        body: formData
                    });

                    const result = await response.json();

                    if (!response.ok) {
                        throw result;
                    }

                    alert(editId ? "عملیات با موفقیت ویرایش شد." : "عملیات با موفقیت ثبت شد.");

                    dzAfter.removeAllFiles(true);

                    createOperationModal.hide();

                    await loadRiser();

                } catch (error) {

                    console.log(error);

                }

            });
    </script>

    <script>
        const createOperationModal = new bootstrap.Modal(
            document.getElementById("createOperationModal")
        );

        async function openCreateOperation() {

            document.getElementById("createOperationForm").reset();
            document.getElementById("operation_edit_id").value = '';
            document.getElementById("operationModalTitle").textContent = 'ثبت عملیات جدید';
            document.getElementById("operationSubmitBtn").innerHTML = '<i class="bi bi-save"></i> ذخیره عملیات';

            document.getElementById("operation_riser_id").value = riserData.id;
            loadMaterials();
            loadOperationTypes();
            await loadPendingBeforePhotos();

            const searchInput = document.getElementById('materialSearch');
            searchInput.value = '';
            filterMaterials('');

            createOperationModal.show();

        }

        async function openEditOperation(id) {

            const item = riserData.operations.find(x => x.id == id);
            if (!item) return;

            document.getElementById("createOperationForm").reset();
            document.getElementById("operation_edit_id").value = item.id;
            document.getElementById("operationModalTitle").textContent = 'ویرایش عملیات';
            document.getElementById("operationSubmitBtn").innerHTML = '<i class="bi bi-save"></i> ذخیره تغییرات';

            document.getElementById("operation_riser_id").value = riserData.id;

            loadOperationTypes();
            await loadMaterialsForEdit(item);

            // برای ویرایش، عکس‌های "قبل از عملیات" در انتظار معنا ندارد (قبلاً وصل شده)
            document.getElementById('pendingBeforePhotos').innerHTML =
                '<span class="text-muted small">برای مدیریت عکس‌ها از دکمه «مشاهده» استفاده کن.</span>';

            const searchInput = document.getElementById('materialSearch');
            searchInput.value = '';
            filterMaterials('');

            createOperationModal.show();

            // مقداردهی select و فیلدهای متنی بعد از پر شدن گزینه‌های select (چون async است، کمی تاخیر لازم است)
            setTimeout(() => {
                document.getElementById('operationcat').value = item.opencat.id;
                document.querySelector('[name="status"]').value = item.status;
                if (item.priority) {
                    document.querySelector('[name="priority"]').value = item.priority;
                }
                document.querySelector('[name="description"]').value = item.description ?? '';
            }, 0);
        }

        async function loadMaterialsForEdit(item) {

            const materials = riserData.material;
            let html = '';

            materials.forEach(material => {

                const normalizedTitle = normalizeFaText(material.title);
                const existing = item.materials.find(m => m.id === material.id);
                const qty = existing ? existing.pivot.qty : 0;

                html += `
            <tr class="material-row" data-title="${normalizedTitle}">

                <td>${material.title}</td>

                <td>${material.unit}</td>

                <td>
                    <input
                        type="number"
                        class="form-control"
                        min="0"
                        value="${qty}"
                        name="materials[${material.id}]">
                </td>

            </tr>
        `;

            });

            document.getElementById('materialsContainer').innerHTML = html;
        }

        async function loadPendingBeforePhotos() {

            const container = document.getElementById('pendingBeforePhotos');
            container.innerHTML = 'در حال بارگذاری...';

            try {
                const res = await fetch(`{{ url('riser') }}/${riserData.id}/pending-before-photos`);
                const photos = await res.json();

                container.innerHTML = photos.length ?
                    photos.map(p => `
                        <img src="${p.url}"
                             style="width:70px;height:70px;object-fit:cover;border-radius:6px;"
                             class="border">
                    `).join('') :
                    '<span class="text-muted small">عکس قبل از عملیاتی ثبت نشده.</span>';

            } catch (e) {
                container.innerHTML = '<span class="text-danger small">خطا در بارگذاری عکس‌ها</span>';
            }
        }


        const dzAfter = new Dropzone("#operationDropzoneAfter", {

            url: "/operation/photos/upload",

            paramName: "photo",

            maxFilesize: 10,

            acceptedFiles: "image/*",

            addRemoveLinks: true,

            autoProcessQueue: false

        });
    </script>

    {{-- ============================
    Modal جزئیات عملیات
============================ --}}

    <div class="modal fade" id="operationModal" tabindex="-1">

        <div class="modal-dialog modal-xl">

            <div class="modal-content">

                <div class="modal-header">

                    <h5 class="modal-title">

                        جزئیات عملیات

                    </h5>

                    <button class="btn-close" data-bs-dismiss="modal"></button>

                </div>

                <div class="modal-body">

                    <div id="operationDetail">

                    </div>

                </div>

            </div>

        </div>

    </div>

    <script>
        // Before/after compare slider
        const range = document.getElementById('compareRange');
        const afterLayer = document.querySelector('.after-layer');
        const divider = document.getElementById('divider');
        const handle = document.getElementById('handle');

        if (range) {
            function updateCompare(val) {
                afterLayer.style.clipPath = `inset(0 ${100-val}% 0 0)`;
                divider.style.right = val + '%';
                handle.style.right = val + '%';
            }
            range.addEventListener('input', e => updateCompare(e.target.value));
            updateCompare(50);
        }

        // Reveal on scroll
        const revealEls = document.querySelectorAll('.reveal');
        const io = new IntersectionObserver((entries) => {
            entries.forEach(en => {
                if (en.isIntersecting) {
                    en.target.classList.add('in');
                    io.unobserve(en.target);
                }
            });
        }, {
            threshold: 0.15
        });
        revealEls.forEach(el => io.observe(el));
    </script>

    <script>
        const riserId = {{ $id ?? request()->route('id') }};
        const riserDetailsRoute = @json(route('riser.details', ['id' => '__ID__']));

        let operationModal = new bootstrap.Modal(
            document.getElementById('operationModal')
        );

        let riserData = {};

        loadRiser();


        async function loadRiser() {
            try {

                const response = await fetch(
                    riserDetailsRoute.replace('__ID__', riserId)
                );

                riserData = await response.json();

                loadInfo();
                loadMiniMap();

                loadGallery();
                loadWorkflow();
                loadOperations();

                loadSupervisors();

            } catch (e) {

                console.log(e);

            }

        }




        function loadInfo() {

            document.getElementById('riserCode').innerHTML = riserData.code;

            document.getElementById('infoCode').innerHTML = riserData.code;


            document.getElementById('riserStatus').innerHTML = riserData.status || 'نامشخص';

        }

        async function loadOperationTypes() {


            const opc = riserData.operation_cat;

            const select = document.getElementById('operationcat');

            select.innerHTML = '<option value="">انتخاب کنید</option>';

            opc.forEach(item => {
                select.innerHTML += `
                    <option value="${item.id}">
                        ${item.title}
                    </option>
                `;
            });

        }

        async function loadMaterials() {

            const materials = riserData.material;

            let html = '';

            materials.forEach(material => {

                const normalizedTitle = normalizeFaText(material.title);

                html += `
            <tr class="material-row" data-title="${normalizedTitle}">

                <td>${material.title}</td>

                <td>${material.unit}</td>

                <td>
                    <input
                        type="number"
                        class="form-control"
                        min="0"
                        value="0"
                        name="materials[${material.id}]">
                </td>

            </tr>
        `;

            });

            document.getElementById('materialsContainer').innerHTML = html;

        }

        // یکسان‌سازی حروف عربی/فارسی رایج (ی/ي، ک/ك) و حذف فاصله‌های اضافه برای جستجوی دقیق‌تر
        function normalizeFaText(text) {
            return (text || '')
                .toString()
                .toLowerCase()
                .replace(/ي/g, 'ی')
                .replace(/ك/g, 'ک')
                .trim();
        }

        function filterMaterials(query) {

            const normalizedQuery = normalizeFaText(query);
            const rows = document.querySelectorAll('#materialsContainer .material-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const matches = row.dataset.title.includes(normalizedQuery);
                row.classList.toggle('d-none', !matches);
                if (matches) visibleCount++;
            });

            document.getElementById('materialSearchEmpty').classList.toggle('d-none', visibleCount !== 0);
        }

        document.getElementById('materialSearch').addEventListener('input', function () {
            filterMaterials(this.value);
        });


        function loadGallery() {
            // پیدا کردن اولین operation ای که عکس داره
            const operationWithPhotos = riserData.operations.find(op => (op.photos ?? []).length > 0);

            const photos = operationWithPhotos?.photos ?? [];

            if (photos.length === 0) {
                document.getElementById('thumbnailContainer').innerHTML = "";
                return;
            }

            document.getElementById('mainImage').src = photos[0].url;

            let html = '';
            photos.forEach(photo => {
                html += `
            <img
                src="${photo.url}"
                class="thumb"
                onclick="changeImage('${photo.url}')"
            >
        `;
            });
            document.getElementById('thumbnailContainer').innerHTML = html;
        }



        function changeImage(url) {

            document.getElementById('mainImage').src = url;

        }

        function loadWorkflow() {

            let html = '';

            if (!riserData.workflow || riserData.workflow.length === 0) {

                html = `
            <tr>
                <td colspan="7" class="text-center">
                    تاریخچه‌ای وجود ندارد
                </td>
            </tr>
        `;

            } else {

                riserData.workflow.forEach((row, index) => {

                    html += `

            <tr>

                <td>${index + 1}</td>

                <td>
                    ${row.title}
                </td>

                <td>
                    ${row.from_user ?? '-'}
                </td>

                <td>
                    ${row.to_user ?? '-'}
                </td>

                <td>
                    ${row.send_date ?? '-'}
                </td>

                <td>
                    ${
                        row.receive_date
                        ? row.receive_date
                        : '<span class="badge bg-warning">در انتظار</span>'
                    }
                </td>

                <td>
                    ${
                        row.comment
                        ? row.comment
                        : '-'
                    }
                </td>

                <td>
                    ${
                        row.status === 'دریافت شده'
                        ?
                        `<span class="badge bg-success">${row.status}</span>`
                        :
                        `<span class="badge bg-warning">${row.status}</span>`
                    }
                </td>

            </tr>

            `;

                });

            }


            document.querySelector("#workflowTable tbody").innerHTML = html;

        }



        function loadOperations() {
            let html = '';

            riserData.operations.forEach((row, index) => {

                html += `

        <tr>

            <td>${index+1}</td>

            <td>${row.date}</td>

            <td>${row.opencat.title}</td>
            
            <td>${row.user.name}</td>

            <td>

                <span class=" bg-gray">

                    ${row.status}

                </span>

            </td>

            <td>
                ${
                    row.materials.length
                        ? row.materials.map(material => `
                            <div>
                                ${material.title}
                                (${material.pivot.qty} ${material.unit})
                            </div>
                        `).join('')
                        : '-'
                }
            </td>

            <td>

                <button

                    class="btn btn-primary btn-sm"

                    onclick="showOperation(${row.id})"

                >

                    مشاهده

                </button>

                <button

                    class="btn btn-outline-secondary btn-sm"

                    onclick="openEditOperation(${row.id})"

                >

                    <i class="bi bi-pencil"></i>
                    ویرایش

                </button>

            </td>


        </tr>

        `;

            });

            document.querySelector("#operationTable tbody").innerHTML = html;

        }



        function loadSupervisors() {

            let html = '';

            riserData.supervisors.forEach((row, index) => {

                html += `

        <tr>

            <td>${index+1}</td>

            <td>${row.operation}</td>

            <td>${row.name}</td>

            <td>${row.position}</td>

            <td>${row.date}</td>

            <td>

                <span class="badge bg-success">

                    ${row.result}

                </span>

            </td>

        </tr>

        `;

            });

            document.querySelector("#supervisorTable tbody").innerHTML = html;

        }

        function loadMiniMap() {
            const coordinate = riserData.coordinate;

            if (!coordinate || !coordinate.coordinates || coordinate.coordinates.length < 2) {
                document.getElementById('miniMap').innerHTML = '<p class="text-center pt-5">مختصات موجود نیست</p>';
                return;
            }

            const [lng, lat] = coordinate.coordinates;
            const coord = ol.proj.fromLonLat([lng, lat]);

            const marker = new ol.Feature({
                geometry: new ol.geom.Point(coord),
            });
            marker.setStyle(new ol.style.Style({
                image: new ol.style.Circle({
                    radius: 8,
                    fill: new ol.style.Fill({
                        color: '#dc3545',
                    }),
                    stroke: new ol.style.Stroke({
                        color: '#fff',
                        width: 2,
                    }),
                }),
            }));

            const vectorLayer = new ol.layer.Vector({
                source: new ol.source.Vector({
                    features: [marker]
                }),
            });

            const osm = new ol.layer.Tile({
                source: new ol.source.XYZ({
                    url: '{{ url('/tiles/{z}/{x}/{y}.png') }}'
                })
            });


            const map = new ol.Map({
                target: 'miniMap',
                layers: [
                    osm,
                    vectorLayer,
                ],
                view: new ol.View({
                    center: coord,
                    zoom: 17,
                }),
                interactions: ol.interaction.defaults.defaults({
                    mouseWheelZoom: false,
                }),
            });

            return map;
        }

        function showOperation(id) {

            const item = riserData.operations.find(x => x.id == id);

            if (!item)
                return;

            const renderPhotoList = (photos) => (photos && photos.length) ?
                photos.map(p => `
                    <div class="position-relative d-inline-block me-2 mb-2" data-photo-id="${p.id}">
                        <img src="${p.url}" style="width:90px;height:90px;object-fit:cover;border-radius:8px;">
                        <button type="button"
                                class="btn btn-sm btn-danger position-absolute top-0 end-0 p-0 px-1"
                                onclick="deleteOperationPhoto(${p.id}, this)">×</button>
                    </div>
                `).join('') :
                '<span class="text-muted">عکسی ثبت نشده</span>';

            const beforePhotos = (item.photos || []).filter(p => p.type === 'before');
            const afterPhotos = (item.photos || []).filter(p => p.type === 'after');

            document.getElementById("operationDetail").innerHTML = `

        <table class="table table-bordered">

            <tr>
                <th width="200">عملیات</th>
                <td>${item.opencat.title}</td>
            </tr>

            <tr>
                <th>تاریخ</th>
                <td>${item.date}</td>
            </tr>

            <tr>
                <th>وضعیت</th>
                <td>${item.status}</td>
            </tr>

            <tr>
                <th>توضیحات</th>
                <td>${item.description ?? '-'}</td>
            </tr>

        </table>

        <div class="row mt-3">

            <div class="col-md-6">

                <label class="form-label">
                    <i class="bi bi-camera"></i>
                    عکس‌های قبل از تعمیر
                </label>

                <div id="operationDetailPhotosBefore" class="mb-2">${renderPhotoList(beforePhotos)}</div>

                <div id="operationDetailDropzoneBefore" class="dropzone"></div>

            </div>

            <div class="col-md-6">

                <label class="form-label">
                    <i class="bi bi-camera-fill"></i>
                    عکس‌های بعد از تعمیر
                </label>

                <div id="operationDetailPhotosAfter" class="mb-2">${renderPhotoList(afterPhotos)}</div>

                <div id="operationDetailDropzoneAfter" class="dropzone"></div>

            </div>

        </div>

    `;

            setupDetailDropzone(item.id);

            operationModal.show();

        }

        // -------- آپلود عکس بعد از ثبت عملیات (داخل مودال جزئیات، هر دو ستون) --------

        let detailDzBefore = null;
        let detailDzAfter = null;

        function buildDetailDropzone(elementId, operationId, type, previewContainerId) {

            const dz = new Dropzone(elementId, {
                url: "{{ url('operation/photos/upload') }}",
                paramName: "photo",
                maxFilesize: 10,
                acceptedFiles: "image/*",
                autoProcessQueue: true,
                headers: {
                    'X-CSRF-TOKEN': "{{ csrf_token() }}"
                },
            });

            dz.on("sending", function(file, xhr, formData) {
                formData.append('operation_id', operationId);
                formData.append('type', type);
            });

            dz.on("success", function(file, response) {
                const wrap = document.createElement('div');
                wrap.className = 'position-relative d-inline-block me-2 mb-2';
                wrap.dataset.photoId = response.id;
                wrap.innerHTML = `
            <img src="${response.url}" style="width:90px;height:90px;object-fit:cover;border-radius:8px;">
            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 p-0 px-1">×</button>
        `;
                wrap.querySelector('button').addEventListener('click', function() {
                    deleteOperationPhoto(response.id, this);
                });
                document.getElementById(previewContainerId).appendChild(wrap);
                file.previewElement.remove();
            });

            dz.on("error", function(file) {
                alert('آپلود عکس با خطا مواجه شد.');
                file.previewElement.remove();
            });

            return dz;
        }

        function setupDetailDropzone(operationId) {

            if (detailDzBefore) {
                detailDzBefore.destroy();
                detailDzBefore = null;
            }

            if (detailDzAfter) {
                detailDzAfter.destroy();
                detailDzAfter = null;
            }

            detailDzBefore = buildDetailDropzone(
                "#operationDetailDropzoneBefore", operationId, 'before', 'operationDetailPhotosBefore'
            );

            detailDzAfter = buildDetailDropzone(
                "#operationDetailDropzoneAfter", operationId, 'after', 'operationDetailPhotosAfter'
            );
        }

        async function deleteOperationPhoto(id, btn) {

            if (!confirm('این تصویر حذف شود؟')) return;

            try {

                const response = await fetch(`/operation/photos/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                });

                if (!response.ok) throw new Error('delete failed');

                btn.closest('[data-photo-id]').remove();

            } catch (e) {
                console.log(e);
                alert('حذف تصویر با خطا مواجه شد.');
            }
        }
    </script>

@endsection
