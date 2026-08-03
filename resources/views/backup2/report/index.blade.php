@extends('layouts.app')

@section('title','گزارشات')

@section('content')

<div class="page-eyebrow">REPORTS</div>
<div class="page-heading">

    <div>
        <h4 class="mb-1"><i class="bi bi-bar-chart-line-fill text-primary"></i> گزارشات سیستم</h4>
        <small class="text-muted">گزارش عملیات، رایزرها و وضعیت پروژه</small>
    </div>

    <div class="d-flex gap-2 flex-wrap">
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


<div class="card-box mb-4">

    <div class="card-header">
        <i class="bi bi-funnel-fill"></i>
        فیلتر گزارش
    </div>

    <div class="card-body p-3">

        <div class="row g-3">

            <div class="col-6 col-lg-2">
                <label class="form-label">از تاریخ</label>
                <input type="date" id="fromDate" class="form-control">
            </div>

            <div class="col-6 col-lg-2">
                <label class="form-label">تا تاریخ</label>
                <input type="date" id="toDate" class="form-control">
            </div>

            <div class="col-6 col-lg-2">
                <label class="form-label">وضعیت</label>
                <select id="status" class="form-select">
                    <option value="">همه</option>
                    <option>انجام شده</option>
                    <option>درحال انجام</option>
                    <option>اتمام</option>
                </select>
            </div>

            <div class="col-6 col-lg-2">
                <label class="form-label">اولویت</label>
                <select id="priority" class="form-select">
                    <option value="">همه</option>
                    <option>کم</option>
                    <option>متوسط</option>
                    <option>زیاد</option>
                    <option>فوری</option>
                </select>
            </div>

            <div class="col-6 col-lg-2">
                <label class="form-label">پیمانکار</label>
                <select id="contractor" class="form-select">
                    <option value="">همه</option>
                </select>
            </div>

            <div class="col-6 col-lg-2 d-grid">
                <label class="form-label">&nbsp;</label>
                <button id="btnSearch" class="btn btn-primary">
                    <i class="bi bi-search"></i>
                    جستجو
                </button>
            </div>

        </div>

    </div>

</div>


<div class="row g-3 mb-4">

    <div class="col-6 col-lg-2">
        <div class="stat-card">
            <div class="stat-value" id="totalOperation">۰</div>
            <div class="stat-label">کل عملیات</div>
        </div>
    </div>

    <div class="col-6 col-lg-2">
        <div class="stat-card">
            <div class="stat-value" style="color: var(--success);" id="doneOperation">۰</div>
            <div class="stat-label">انجام شده</div>
        </div>
    </div>

    <div class="col-6 col-lg-2">
        <div class="stat-card">
            <div class="stat-value" style="color: var(--warning);" id="progressOperation">۰</div>
            <div class="stat-label">درحال انجام</div>
        </div>
    </div>

    <div class="col-6 col-lg-2">
        <div class="stat-card">
            <div class="stat-value" style="color: var(--danger);" id="stopOperation">۰</div>
            <div class="stat-label">اتمام</div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="stat-card">
            <div class="stat-value" id="totalCost">۰</div>
            <div class="stat-label">مجموع هزینه</div>
        </div>
    </div>

</div>


<div class="row g-3 mb-4">

    <div class="col-lg-6">
        <div class="card-box">
            <div class="card-header">عملیات ماهانه</div>
            <div class="card-body p-3">
                <canvas id="monthChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-box">
            <div class="card-header">وضعیت عملیات</div>
            <div class="card-body p-3">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

</div>


<div class="card-box mb-4">

    <div class="card-header">لیست عملیات</div>

    <div class="card-body p-3">
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle mb-0" id="reportTable">
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

</div>


<div class="card-box">

    <div class="card-header">موقعیت عملیات روی نقشه</div>

    <div class="card-body p-0">
        <div id="map" style="height:480px; border-radius: 0 0 12px 12px; overflow: hidden;"></div>
    </div>

</div>

@endsection


@push('styles')
<style>
#map { width: 100%; }
canvas { max-height: 300px; }
.table th { white-space: nowrap; }
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
