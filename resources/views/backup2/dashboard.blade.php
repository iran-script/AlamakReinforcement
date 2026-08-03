@extends('layouts.app')

@section('title', 'داشبورد')

@section('content')

<div class="page-eyebrow">OVERVIEW</div>
<div class="page-heading">
    <h4><i class="bi bi-speedometer2 text-primary"></i> داشبورد سیستم مقاوم‌سازی علمک‌ها</h4>
</div>

<div class="row g-3">

    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-value">۱۲,۰۰۰</div>
            <div class="stat-label"><i class="bi bi-collection"></i> کل علمک‌ها</div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-value" style="color: var(--success);">۳,۵۰۰</div>
            <div class="stat-label"><i class="bi bi-check-circle"></i> انجام شده</div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-value" style="color: var(--warning);">۸,۵۰۰</div>
            <div class="stat-label"><i class="bi bi-hourglass-split"></i> در انتظار</div>
        </div>
    </div>

    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-value" style="color: var(--primary);">۴۵</div>
            <div class="stat-label"><i class="bi bi-lightning-charge"></i> عملیات امروز</div>
        </div>
    </div>

</div>

<div class="row g-3 mt-1">

    <div class="col-lg-6">
        <div class="card-box h-100">
            <div class="p-3 pb-0">
                <h5 class="mb-0"><i class="bi bi-clock-history text-primary"></i> آخرین عملیات‌ها</h5>
            </div>

            <div class="p-3">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="plate">AMK-1001</span>
                        <span class="status-pill status-done">انجام شد</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="plate">AMK-1002</span>
                        <span class="status-pill status-pending">در حال انجام</span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span class="plate">AMK-1003</span>
                        <span class="status-pill status-done">انجام شد</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card-box h-100">
            <div class="p-3 pb-0">
                <h5 class="mb-0"><i class="bi bi-graph-up text-primary"></i> پیشرفت پروژه</h5>
            </div>

            <div class="p-3">
                <div class="d-flex justify-content-between mb-2" style="font-size: 13px; color: var(--text-muted);">
                    <span>۴۰٪ تکمیل شده</span>
                    <span>۱۲,۰۰۰ علمک</span>
                </div>
                <div class="progress" style="height: 10px; border-radius: 999px;">
                    <div class="progress-bar" style="width: 40%; background: var(--primary); border-radius: 999px;"></div>
                </div>
            </div>
        </div>
    </div>

</div>

@endsection
