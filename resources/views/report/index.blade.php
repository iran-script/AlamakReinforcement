@extends('layouts.app')

@section('title','گزارشات')

@section('content')


<div class="container-fluid py-3">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <div>
            <h3 class="mb-1">
                <i class="bi bi-bar-chart-line-fill text-primary"></i>
                گزارشات سیستم
            </h3>

            <small class="text-muted">
                گزارش عملیات، رایزرها و وضعیت پروژه
            </small>
        </div>

        <div>

            <button class="btn btn-success" id="btnExcel">
                <i class="bi bi-file-earmark-excel"></i>
                Excel
            </button>

            <button class="btn btn-danger" id="btnPdf">
                <i class="bi bi-file-earmark-pdf"></i>
                PDF
            </button>

            <button class="btn btn-secondary" onclick="window.print()">
                <i class="bi bi-printer"></i>
                چاپ
            </button>

        </div>

    </div>



    <div class="card shadow-sm mb-4">

        <div class="card-header">

            <strong>

                <i class="bi bi-funnel-fill"></i>

                فیلتر گزارش

            </strong>

        </div>

        <div class="card-body">

            <div class="row g-3">

                <div class="col-lg-2">

                    <label class="form-label">
                        از تاریخ
                    </label>

                    <input
                        type="date"
                        id="fromDate"
                        class="form-control">

                </div>


                <div class="col-lg-2">

                    <label class="form-label">
                        تا تاریخ
                    </label>

                    <input
                        type="date"
                        id="toDate"
                        class="form-control">

                </div>


                <div class="col-lg-2">

                    <label class="form-label">

                        وضعیت

                    </label>

                    <select
                        id="status"
                        class="form-select">

                        <option value="">
                            همه
                        </option>

                        <option>
                            انجام شده
                        </option>

                        <option>
                            درحال انجام
                        </option>

                        <option>
                            اتمام
                        </option>

                    </select>

                </div>



                <div class="col-lg-2">

                    <label class="form-label">

                        اولویت

                    </label>

                    <select
                        id="priority"
                        class="form-select">

                        <option value="">
                            همه
                        </option>

                        <option>
                            کم
                        </option>

                        <option>
                            متوسط
                        </option>

                        <option>
                            زیاد
                        </option>

                        <option>
                            فوری
                        </option>

                    </select>

                </div>



                <div class="col-lg-2">

                    <label class="form-label">

                        پیمانکار

                    </label>

                    <select
                        id="contractor"
                        class="form-select">

                        <option value="">
                            همه
                        </option>

                    </select>

                </div>


                <div class="col-lg-2 d-grid">

                    <label class="form-label">

                        &nbsp;

                    </label>

                    <button
                        id="btnSearch"
                        class="btn btn-primary">

                        <i class="bi bi-search"></i>

                        جستجو

                    </button>

                </div>

            </div>

        </div>

    </div>




    <div class="row mb-4">

        <div class="col-lg-2">

            <div class="card border-0 shadow text-center">

                <div class="card-body">

                    <h6>

                        کل عملیات

                    </h6>

                    <h2 id="totalOperation">

                        0

                    </h2>

                </div>

            </div>

        </div>


        <div class="col-lg-2">

            <div class="card border-0 shadow text-center">

                <div class="card-body">

                    <h6>

                        انجام شده

                    </h6>

                    <h2
                        class="text-success"
                        id="doneOperation">

                        0

                    </h2>

                </div>

            </div>

        </div>



        <div class="col-lg-2">

            <div class="card border-0 shadow text-center">

                <div class="card-body">

                    <h6>

                        درحال انجام

                    </h6>

                    <h2
                        class="text-warning"
                        id="progressOperation">

                        0

                    </h2>

                </div>

            </div>

        </div>



        <div class="col-lg-2">

            <div class="card border-0 shadow text-center">

                <div class="card-body">

                    <h6>

                        اتمام

                    </h6>

                    <h2
                        class="text-danger"
                        id="stopOperation">

                        0

                    </h2>

                </div>

            </div>

        </div>



        <div class="col-lg-4">

            <div class="card border-0 shadow text-center">

                <div class="card-body">

                    <h6>

                        مجموع هزینه

                    </h6>

                    <h2
                        id="totalCost">

                        0

                    </h2>

                </div>

            </div>

        </div>

    </div>





    <div class="row mb-4">

        <div class="col-lg-6">

            <div class="card shadow">

                <div class="card-header">

                    عملیات ماهانه

                </div>

                <div class="card-body">

                    <canvas id="monthChart"></canvas>

                </div>

            </div>

        </div>



        <div class="col-lg-6">

            <div class="card shadow">

                <div class="card-header">

                    وضعیت عملیات

                </div>

                <div class="card-body">

                    <canvas id="statusChart"></canvas>

                </div>

            </div>

        </div>

    </div>





    <div class="card shadow mb-4">

        <div class="card-header">

            لیست عملیات

        </div>

        <div class="card-body">

            <table
                class="table table-striped table-bordered align-middle"
                id="reportTable">

                <thead>

                <tr>

                    <th>#</th>

                    <th>کد رایزر</th>

                    <th>عملیات</th>

                    <th>تاریخ</th>

                    <th>وضعیت</th>

                    <th>اولویت</th>

                    <th>پیمانکار</th>

                    <th>شیر</th>

                    <th>مهره</th>

                    <th>هزینه</th>

                </tr>

                </thead>

                <tbody>

                </tbody>

            </table>

        </div>

    </div>




    <div class="card shadow">

        <div class="card-header">

            موقعیت عملیات روی نقشه

        </div>

        <div class="card-body p-0">

            <div
                id="map"
                style="height:550px"></div>

        </div>

    </div>

</div>

@endsection


@push('styles')

<style>

.card{

    border-radius:12px;

}

.card-header{

    font-weight:bold;

}

#map{

    width:100%;

}

canvas{

    max-height:320px;

}

.table th{

    white-space:nowrap;

}

</style>

@endpush


@push('scripts')

<script>

document
.getElementById("btnSearch")
.addEventListener("click",function(){

    loadReport();

});

document
.getElementById("btnExcel")
.addEventListener("click",function(){

    // بخش بعدی

});

document
.getElementById("btnPdf")
.addEventListener("click",function(){

    // بخش بعدی

});

</script>

@endpush