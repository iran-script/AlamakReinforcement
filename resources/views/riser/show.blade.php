@extends('layouts.app')

@section('title', 'جزئیات علمک')

@section('content')

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/min/dropzone.min.css">

    <script src="https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/min/dropzone.min.js"></script>


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

                        <img id="mainImage" src="https://placehold.co/1200x700?text=No+Image" class="main-image">

                        <div class="d-flex gap-2 mt-3 flex-wrap" id="thumbnailContainer">

                            <img src="https://placehold.co/120x120" class="thumb">

                            <img src="https://placehold.co/120x120" class="thumb">

                            <img src="https://placehold.co/120x120" class="thumb">

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
                        <div class="d-flex justify-content-end mb-3">
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

                                        <th>هزینه</th>

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

            <td>${row.operation}</td>

            <td>${row.contractor}</td>

            <td>

                <span class="badge bg-success">

                    ${row.status}

                </span>

            </td>

            <td>

                ${row.cost}

            </td>

            <td>

                <button

                    class="btn btn-primary btn-sm"

                    onclick="showOperation(${row.id})"

                >

                    مشاهده

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
