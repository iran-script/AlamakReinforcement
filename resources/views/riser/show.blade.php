@extends('layouts.app')

@section('title', 'جزئیات علمک')

@section('content')

    <link rel="stylesheet" href="{{ url('css/dropzone.css') }}">
    <script src="{{  url('js/dropzone.js')  }}"></script>


    <style>
        .page-header {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 22px 24px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .page-header::before {
            content: "";
            position: absolute; inset-inline-start: 0; top: 0; bottom: 0;
            width: 4px; background: var(--primary);
        }

        .page-title {
            font-size: 22px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
        }

        .meta-line {
            color: var(--text-muted);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .card-custom {
            border: 1px solid var(--border);
            border-radius: 16px;
            margin-bottom: 20px;
            background: var(--surface);
            box-shadow: var(--shadow-sm);
        }

        .card-custom .card-header {
            background: var(--surface-2);
            border-bottom: 1px solid var(--border);
            border-radius: 16px 16px 0 0 !important;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ===== EVIDENCE VIEWER (gallery) ===== */
        .evidence-frame {
            position: relative;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--border);
            background: var(--surface-2);
        }

        .main-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            display: block;
            cursor: zoom-in;
        }

        .evidence-corner {
            position: absolute;
            width: 20px; height: 20px;
            border-color: rgba(62,99,221,.75);
            border-style: solid;
            border-width: 0;
            z-index: 2;
            pointer-events: none;
        }
        .evidence-corner.tl { top: 10px; inset-inline-start: 10px; border-top-width: 2px; border-inline-start-width: 2px; }
        .evidence-corner.tr { top: 10px; inset-inline-end: 10px; border-top-width: 2px; border-inline-end-width: 2px; }
        .evidence-corner.bl { bottom: 10px; inset-inline-start: 10px; border-bottom-width: 2px; border-inline-start-width: 2px; }
        .evidence-corner.br { bottom: 10px; inset-inline-end: 10px; border-bottom-width: 2px; border-inline-end-width: 2px; }

        .filmstrip {
            display: flex;
            gap: 10px;
            margin-top: 14px;
            flex-wrap: wrap;
        }

        .thumb {
            width: 84px;
            height: 84px;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            border: 2px solid var(--border);
            transition: .15s;
            opacity: .8;
        }

        .thumb:hover { opacity: 1; transform: translateY(-2px); }
        .thumb.active { border-color: var(--primary); opacity: 1; box-shadow: 0 0 0 3px var(--primary-soft); }

        .empty-gallery {
            padding: 40px;
            text-align: center;
            color: var(--text-faint);
            font-size: 13px;
        }

        /* ===== INFO SIDEBAR ===== */
        .info-item { margin-bottom: 16px; }
        .info-label {
            font-weight: 700;
            color: var(--text-faint);
            font-size: 11.5px;
            letter-spacing: .02em;
            margin-bottom: 3px;
        }
        .info-value { font-size: 14.5px; color: var(--text); }

        .mini-map-placeholder {
            height: 300px;
            border-radius: 0 0 15px 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--text-faint);
            font-size: 13px;
            background: var(--surface-2);
        }
        .mini-map-placeholder i { font-size: 24px; color: var(--text-faint); }

        #materialsContainer input[type="number"] { max-width: 130px; }

        .table-empty-note { color: var(--text-faint); font-size: 13px; }

        @media (max-width: 575.98px) {
            .main-image { height: 260px; }
        }
    </style>


    <div class="page-eyebrow">RISER DOSSIER / پرونده فنی علمک</div>

    <!-- HEADER -->
    <div class="page-header">

        <div class="row align-items-center">

            <div class="col-lg-8">

                <h2 class="page-title mb-0">
                    <i class="bi bi-geo-alt-fill text-primary"></i>
                    <span class="plate plate-lg" id="riserCode">---</span>
                </h2>

                <div class="d-flex flex-wrap gap-3 mt-3">

                    <span class="status-pill status-done" id="riserStatus">
                        تعمیر شده
                    </span>

                    <span class="meta-line">
                        <i class="bi bi-calendar-event"></i>
                        آخرین بروزرسانی:
                        <span id="updatedAt">---</span>
                    </span>

                </div>

            </div>

            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">

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
            <div class="card-custom">

                <div class="card-header">
                    <i class="bi bi-images text-primary"></i>
                    تصاویر علمک
                </div>

                <div class="card-body p-3">

                    <div class="evidence-frame">
                        <div class="evidence-corner tl"></div>
                        <div class="evidence-corner tr"></div>
                        <div class="evidence-corner bl"></div>
                        <div class="evidence-corner br"></div>
                        <img id="mainImage" src="{{ url('img/alamak.jpg') }}" class="main-image">
                    </div>

                    <div class="filmstrip" id="thumbnailContainer">

                        <img src="{{ url('img/alamak.jpg') }}" class="thumb active">

                        <img src="{{ url('img/alamak.jpg') }}" class="thumb">

                        <img src="{{ url('img/alamak.jpg') }}" class="thumb">

                    </div>

                </div>

            </div>



            <!-- OPERATIONS -->
            <div class="card-custom">

                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span>
                        <i class="bi bi-tools text-primary"></i>
                        عملیات انجام شده
                    </span>

                    @if (!auth()->user()->hasRole('supervisor'))
                        <button class="btn btn-success btn-sm" onclick="openCreateOperation()">
                            <i class="bi bi-plus-circle"></i>
                            عملیات جدید
                        </button>
                    @endif
                </div>

                <div class="card-body p-3">

                    <div class="table-responsive">

                        <table class="table table-bordered table-hover align-middle mb-0" id="operationTable">

                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>تاریخ</th>
                                    <th>نوع عملیات</th>
                                    <th>پیمانکار</th>
                                    <th>وضعیت</th>
                                    <th>اقلام مصرفی</th>
                                    <th>جزئیات</th>
                                    @if (auth()->user()->hasRole('supervisor'))
                                        <th>تایید یا رد</th>
                                    @endif
                                </tr>
                            </thead>

                            <tbody></tbody>

                        </table>
                    </div>

                </div>

            </div>



            <!-- SUPERVISORS -->
            <div class="card-custom">

                <div class="card-header">
                    <i class="bi bi-person-check-fill text-primary"></i>
                    تاییدکنندگان عملیات
                </div>

                <div class="card-body p-3">

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped mb-0" id="supervisorTable">

                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>عملیات</th>
                                    <th>ناظر</th>
                                    <th>سمت</th>
                                    <th>تاریخ تایید</th>
                                    <th>نتیجه</th>
                                </tr>
                            </thead>

                            <tbody></tbody>

                        </table>
                    </div>

                </div>

            </div>

        </div>



        <!-- RIGHT -->
        <div class="col-lg-4">

            <div class="card-custom">

                <div class="card-header">
                    <i class="bi bi-info-circle-fill text-primary"></i>
                    مشخصات علمک
                </div>

                <div class="card-body p-3">

                    <div class="info-item">
                        <div class="info-label">کد علمک</div>
                        <div class="info-value" id="infoCode">-</div>
                    </div>

                </div>

            </div>



            <div class="card-custom">

                <div class="card-header">
                    <i class="bi bi-geo-alt text-primary"></i>
                    موقعیت
                </div>

                <div class="card-body p-0">

                    <div id="miniMap" class="mini-map-placeholder">
                        <i class="bi bi-pin-map"></i>
                        پیش‌نمایش موقعیت روی نقشه
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

                <div class="modal-content">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-plus-circle text-primary"></i>
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
                                <label class="form-label">متریال مصرفی</label>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>متریال</th>
                                                <th width="120">واحد</th>
                                                <th width="150">تعداد</th>
                                            </tr>
                                        </thead>

                                        <tbody id="materialsContainer"></tbody>
                                    </table>
                                </div>

                            </div>

                            <div class="col-md-12">
                                <label class="form-label">توضیحات</label>
                                <textarea class="form-control" rows="4" name="description"></textarea>
                            </div>

                            <div class="mb-3 col-12">
                                <label class="form-label">عکس‌های عملیات</label>
                                <div id="operationDropzone" class="dropzone"></div>
                            </div>

                        </div>

                    </div>

                    <div class="modal-footer">

                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            انصراف
                        </button>

                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i>
                            ذخیره عملیات
                        </button>

                    </div>

                </div>

            </form>

        </div>
    </div>

    <script>
        document.getElementById("createOperationForm")
            .addEventListener("submit", async function(e) {

                e.preventDefault();

                const form = this;
                const formData = new FormData(form);

                // فایل‌های Dropzone
                dz.getAcceptedFiles().forEach(file => {
                    formData.append('photos[]', file);
                });

                try {

                    const response = await fetch(form.action, {
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

                    alert("عملیات با موفقیت ثبت شد.");

                    dz.removeAllFiles(true);

                    createOperationModal.hide();

                    await loadRiser();

                } catch (error) {

                    console.log(error);

                    alert(error?.message || "خطا در ثبت عملیات. لطفاً دوباره تلاش کنید.");

                }

            });
    </script>

    <script>
        const createOperationModal = new bootstrap.Modal(
            document.getElementById("createOperationModal")
        );

        function openCreateOperation() {

            document.getElementById("createOperationForm").reset();

            document.getElementById("operation_riser_id").value = riserData.id;
            loadMaterials();
            loadOperationTypes();


            createOperationModal.show();


        }


        Dropzone.autoDiscover = false;

        const dz = new Dropzone("#operationDropzone", {

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
                        <i class="bi bi-clipboard-data text-primary"></i>
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

                loadGallery();

                loadOperations();

                loadSupervisors();

            } catch (e) {

                console.log(e);

            }

        }




        function loadInfo() {

            document.getElementById('riserCode').innerHTML = riserData.code;

            document.getElementById('infoCode').innerHTML = riserData.code;

            const statusEl = document.getElementById('riserStatus');
            const status = riserData.status || 'نامشخص';

            statusEl.innerHTML = status;
            statusEl.className = 'status-pill ' + statusPillForStatus(status);

        }

        function statusPillForStatus(status) {

            if (['انجام شده', 'تعمیر شده', 'تایید'].includes(status)) {
                return 'status-done';
            }

            if (['رد شده', 'رد', 'متوقف شده'].includes(status)) {
                return 'status-reject';
            }

            return 'status-pending';
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

                html += `
            <tr>

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




        function loadGallery() {

            const photos = riserData.photos ?? [];

            if (photos.length === 0) {
                document.getElementById('thumbnailContainer').innerHTML =
                    '<div class="empty-gallery">عکسی وجود ندارد</div>';
                return;
            }

            document.getElementById('mainImage').src = photos[0].url;

            let html = '';

            photos.forEach((photo, index) => {

                html += `

            <img

                src="${photo.url}"

                class="thumb ${index === 0 ? 'active' : ''}"

                onclick="changeImage('${photo.url}', this)"

            >

        `;

            });

            document.getElementById('thumbnailContainer').innerHTML = html;

        }



        function changeImage(url, el) {

            document.getElementById('mainImage').src = url;

            document.querySelectorAll('#thumbnailContainer .thumb').forEach(t => t.classList.remove('active'));

            if (el) {
                el.classList.add('active');
            }

        }



        function loadOperations() {
            let html = '';

            if (!riserData.operations || riserData.operations.length === 0) {
                document.querySelector("#operationTable tbody").innerHTML =
                    `<tr><td colspan="8" class="text-center table-empty-note py-4">هنوز عملیاتی ثبت نشده است</td></tr>`;
                return;
            }

            riserData.operations.forEach((row, index) => {

                html += `

        <tr>

            <td>${index+1}</td>

            <td>${row.date}</td>

            <td>${row.opencat.title}</td>
            
            <td>${row.user.name}</td>

            <td>

                <span class="status-pill ${statusPillForStatus(row.status)}">

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

            </td>
            @if (auth()->user()->hasRole('supervisor'))
            <td>

               <button

                    class="btn btn-success btn-sm"

                    onclick="verifyOperation(${row.id},'success')"

                >

                   تایید

                </button>

                 <button

                    class="btn btn-danger btn-sm"

                    onclick="verifyOperation(${row.id},'reject')"

                >

                   رد

                </button>

            </td>
            @endif

        </tr>

        `;

            });

            document.querySelector("#operationTable tbody").innerHTML = html;

        }



        function loadSupervisors() {

            let html = '';

            if (!riserData.supervisors || riserData.supervisors.length === 0) {
                document.querySelector("#supervisorTable tbody").innerHTML =
                    `<tr><td colspan="6" class="text-center table-empty-note py-4">هنوز تاییدی ثبت نشده است</td></tr>`;
                return;
            }

            riserData.supervisors.forEach((row, index) => {

                html += `

        <tr>

            <td>${index+1}</td>

            <td>${row.operation}</td>

            <td>${row.name}</td>

            <td>${row.position}</td>

            <td>${row.date}</td>

            <td>

                <span class="status-pill ${statusPillForStatus(row.result)}">

                    ${row.result}

                </span>

            </td>

        </tr>

        `;

            });

            document.querySelector("#supervisorTable tbody").innerHTML = html;

        }



        function showOperation(id) {

            const item = riserData.operations.find(x => x.id == id);

            if (!item)
                return;

            document.getElementById("operationDetail").innerHTML = `

        <table class="table table-bordered mb-0">

            <tr>

                <th width="200">

                    عملیات

                </th>

                <td>

                    ${item.operation}

                </td>

            </tr>

            <tr>

                <th>

                    تاریخ

                </th>

                <td>

                    ${item.date}

                </td>

            </tr>

            <tr>

                <th>

                    پیمانکار

                </th>

                <td>

                    ${item.contractor}

                </td>

            </tr>

            <tr>

                <th>

                    هزینه

                </th>

                <td>

                    ${item.cost}

                </td>

            </tr>

            <tr>

                <th>

                    وضعیت

                </th>

                <td>

                    <span class="status-pill ${statusPillForStatus(item.status)}">${item.status}</span>

                </td>

            </tr>

            <tr>

                <th>

                    توضیحات

                </th>

                <td>

                    ${item.description}

                </td>

            </tr>

        </table>

    `;

            operationModal.show();

        }

        async function verifyOperation(operationId, result) {

            if (!confirm(result === 'success' ? 'آیا از تایید این عملیات مطمئن هستید؟' : 'آیا از رد این عملیات مطمئن هستید؟')) {
                return;
            }

            try {

                const response = await fetch(`/operations/${operationId}/verify`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ result })
                });

                const data = await response.json();

                if (!response.ok) {
                    throw data;
                }

                alert(result === 'success' ? 'عملیات تایید شد.' : 'عملیات رد شد.');

                await loadRiser();

            } catch (error) {

                console.log(error);
                alert(error?.message || 'خطا در ثبت نتیجه بررسی.');

            }

        }
    </script>

@endsection
