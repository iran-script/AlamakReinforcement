@extends('layouts.app')

@section('title', 'جزئیات علمک')

@section('content')

    <link href="{{ url('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ url('css/bootstrap-icon.css') }}" rel="stylesheet">
    <script src="{{ url('js/bootstrap.js') }}"></script>
    <link rel="stylesheet" href="{{ url('css/dropzone.css') }}">

    <script src="{{ url('js/dropzone.js') }}"></script>


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

        /* ===== SHOWCASE (signature element) ===== */
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
                {{-- <section id="showcase" class="showcase">
                    <div class="wrap">
                        
                        <div class="showcase-glow"></div>
                        <div class="report-card reveal">
                            <div class="report-meta">
                                <div class="m-item">کد علمک <b>OLK-3391-B</b></div>
                                <div class="m-item">منطقه <b style="direction:rtl;font-family:'Vazirmatn'">شهرک صنعتی — بلوک
                                        ۴</b></div>
                                <div class="m-item">تاریخ تعمیر <b>1404/04/28</b></div>
                                <div class="m-item">ناظر <b style="direction:rtl;font-family:'Vazirmatn'">مهندس رضایی</b>
                                </div>
                            </div>

                            <div class="compare" id="compareWidget">
                                <div class="layer before-layer">
                                    <img width="100%" height="100%" id="mainImage" src="{{ url('img/alamak.jpg') }}" class="main-image">
                                </div>
                                <div class="layer after-layer">
                                    <svg width="100%" height="100%" viewBox="0 0 800 400"
                                        preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
                                        <rect width="800" height="400" fill="#EFE9DD" />
                                        <rect x="0" y="330" width="800" height="70" fill="#D8CFBB" />
                                        <rect x="360" y="90" width="80" height="250" fill="#E8B93A" stroke="#B8721B"
                                            stroke-width="2" />
                                        <rect x="345" y="70" width="110" height="30" fill="#D98F2B" stroke="#B8721B"
                                            stroke-width="2" />
                                        <circle cx="400" cy="200" r="3" fill="#8A6C1E" opacity="0.5" />
                                        <path d="M330 340 q20 -10 40 0 t40 0 t40 0 t40 0" stroke="#3F7A5C"
                                            stroke-width="2.5" fill="none" />
                                    </svg>
                                </div>
                                <span class="label before">پیش از تعمیر</span>
                                <span class="label after">پس از تعمیر</span>
                                <div class="divider" id="divider"></div>
                                <div class="handle" id="handle">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                        stroke="#0B2033" stroke-width="2.4">
                                        <path d="M8 7l-5 5 5 5M16 7l5 5-5 5" />
                                    </svg>
                                </div>
                                <input type="range" id="compareRange" min="0" max="100" value="50"
                                    aria-label="مقایسه تصویر پیش و پس از تعمیر">
                            </div>

                            <div class="report-footer">
                                <span class="verdict">تأیید شد — تعویض رایزر و رنگ‌آمیزی استاندارد</span>
                                <span class="note">مصالح مصرفی: لوله فولادی ۲″، رنگ ضدزنگ زرد، بست فلزی ×۲</span>
                            </div>
                        </div>
                    </div>
                </section> --}}
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
                        @if (!auth()->user()->hasRole('supervisor'))
                            <div class="d-flex justify-content-end mb-3">
                                <button class="btn btn-success" onclick="openCreateOperation()">
                                    <i class="bi bi-plus-circle"></i>
                                    عملیات جدید
                                </button>
                            </div>
                        @endif

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

                                        @if (auth()->user()->hasRole('supervisor'))
                                            <th>تایید یا رد</th>
                                        @endif

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

                <div class="modal-content">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

                    <div class="modal-header">
                        <h5 class="modal-title">
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

                                <table class="table table-bordered table-hover align-middle">
                                    <thead class="table-light">
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
                            <div class="col-md-12">
                                <label class="form-label">توضیحات</label>

                                <textarea class="form-control" rows="4" name="description"></textarea>
                            </div>
                            <div class="mb-3">
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

        function updateCompare(val) {
            afterLayer.style.clipPath = `inset(0 ${100-val}% 0 0)`;
            divider.style.right = val + '%';
            handle.style.right = val + '%';
        }
        range.addEventListener('input', e => updateCompare(e.target.value));
        updateCompare(50);

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
                document.getElementById('thumbnailContainer').innerHTML = "";
                return;
            }

            document.getElementById('mainImage').src = photos[0].url;

            if (riserData.photos.length === 0) {
                document.getElementById("gallery").innerHTML = "عکسی وجود ندارد";
                return;
            }



            document.getElementById('mainImage').src = riserData.photos[0].url;

            let html = '';

            riserData.photos.forEach(photo => {

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

                    onclick="verifyOperation(${row.id},'success')"

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



        function showOperation(id) {

            const item = riserData.operations.find(x => x.id == id);

            if (!item)
                return;

            document.getElementById("operationDetail").innerHTML = `

        <table class="table table-bordered">

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

                    ${item.status}

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
    </script>

@endsection
