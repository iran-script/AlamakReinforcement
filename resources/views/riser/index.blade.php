@extends('layouts.app')

@section('title', 'مدیریت علمک‌ها')

@php
    $statusLabels = ['default' => 'پیش‌فرض', 'pending' => 'در انتظار بررسی', 'approved' => 'تأییدشده', 'rejected' => 'ردشده', 'active' => 'فعال', 'inactive' => 'غیرفعال'];
    $sortLabels = ['updated_at' => 'آخرین تغییر', 'created_at' => 'زمان ایجاد', 'id' => 'شناسه', 'r_giscode' => 'کد GIS اصلی', 'giscode' => 'کد GIS', 'status' => 'وضعیت'];
@endphp

@section('content')
<style>
    .riser-page { display: grid; gap: 18px; }
    .riser-hero, .riser-panel, .summary-card { background: var(--surface); border: 1px solid var(--border); border-radius: 16px; box-shadow: var(--shadow-sm); }
    .riser-hero { padding: 22px; display: flex; align-items: center; justify-content: space-between; gap: 18px; }
    .riser-hero h1 { margin: 0; font-size: clamp(1.25rem, 2vw, 1.8rem); font-weight: 850; }
    .riser-hero p { margin: 7px 0 0; color: var(--text-muted); }
    .riser-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
    .summary-card { padding: 16px 18px; }
    .summary-card .label { color: var(--text-muted); font-size: .82rem; }
    .summary-card .value { display: block; margin-top: 7px; font-size: 1.45rem; font-weight: 850; }
    .filter-panel { padding: 18px; }
    .filter-panel summary { cursor: pointer; font-weight: 800; list-style: none; }
    .filter-panel summary::-webkit-details-marker { display: none; }
    .filter-panel summary::before { content: '＋'; margin-inline-end: 7px; color: var(--primary); }
    .filter-panel[open] summary::before { content: '−'; }
    .filter-grid { display: grid; grid-template-columns: 2fr repeat(4, minmax(140px, 1fr)); gap: 12px; margin-top: 16px; align-items: end; }
    .filter-actions { display: flex; gap: 8px; align-items: end; }
    .table-panel { overflow: hidden; }
    .table-panel .table-wrap { overflow-x: auto; }
    .riser-table { margin: 0; min-width: 850px; }
    .riser-table th { white-space: nowrap; background: var(--surface-soft, #f7f8fb); color: var(--text-muted); font-size: .8rem; }
    .riser-table td { vertical-align: middle; }
    .status-chip { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 5px 10px; font-size: .78rem; font-weight: 750; background: #eef1f5; color: #52606d; }
    .status-chip::before { content: ''; width: 7px; height: 7px; border-radius: 50%; background: currentColor; }
    .status-chip.status-active, .status-chip.status-approved { background: #e8f7ef; color: #16834e; }
    .status-chip.status-pending { background: #fff6dc; color: #a56b00; }
    .status-chip.status-rejected, .status-chip.status-inactive { background: #ffebeb; color: #c43d3d; }
    .code-cell { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; direction: ltr; text-align: right; }
    .empty-state { padding: 48px 20px; text-align: center; color: var(--text-muted); }
    .empty-state i { display: block; font-size: 2.2rem; margin-bottom: 10px; opacity: .55; }
    .pagination-wrap { padding: 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
    .pagination-wrap .small { color: var(--text-muted); }
    @media (max-width: 900px) { .filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .filter-grid .search-field { grid-column: 1 / -1; } }
    @media (max-width: 640px) {
        .riser-hero { align-items: flex-start; flex-direction: column; }
        .summary-grid { grid-template-columns: 1fr; }
        .filter-grid { grid-template-columns: 1fr; }
        .filter-grid .search-field { grid-column: auto; }
        .pagination-wrap { align-items: flex-start; flex-direction: column; }
        .riser-table { min-width: 0; }
        .riser-table thead { display: none; }
        .riser-table, .riser-table tbody, .riser-table tr, .riser-table td { display: block; width: 100%; }
        .riser-table tr { border-bottom: 1px solid var(--border); padding: 8px 0; }
        .riser-table td { display: flex; justify-content: space-between; gap: 12px; border: 0; padding: 8px 14px; text-align: start !important; }
        .riser-table td::before { content: attr(data-label); color: var(--text-muted); font-size: .78rem; font-weight: 700; }
        .riser-table td[colspan] { display: block; }
        .riser-table td[colspan]::before { content: none; }
    }
</style>

<div class="riser-page">
    <section class="riser-hero">
        <div>
            <div class="page-eyebrow">GIS / RISERS</div>
            <h1><i class="bi bi-geo-alt text-primary"></i> مدیریت علمک‌ها</h1>
            <p>جست‌وجو و بررسی علمک‌های قابل دسترسی در محدوده مجاز شما</p>
        </div>
        <div class="riser-actions"><a class="btn btn-outline-primary" href="{{ route('map') }}"><i class="bi bi-map"></i> نمایش روی نقشه</a></div>
    </section>

    <section class="summary-grid" aria-label="خلاصه فهرست علمک‌ها">
        <div class="summary-card"><span class="label">کل علمک‌های مجاز</span><strong class="value">{{ number_format($totalVisible) }}</strong></div>
        <div class="summary-card"><span class="label">دارای کد GIS</span><strong class="value">{{ number_format($withCode) }}</strong></div>
        <div class="summary-card"><span class="label">نمایش در این صفحه</span><strong class="value">{{ number_format($risers->count()) }} <small class="text-muted">از {{ number_format($risers->total()) }}</small></strong></div>
    </section>

    <details class="riser-panel filter-panel" open>
        <summary>جست‌وجو و فیلتر پیشرفته</summary>
        <form id="riserFilters" method="GET" action="{{ route('riserIndex') }}">
            <div class="filter-grid">
                <div class="search-field"><label class="form-label" for="riserSearch">جست‌وجوی شناسه یا کد GIS</label><input id="riserSearch" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="شناسه، r_giscode یا giscode" autocomplete="off"></div>
                <div><label class="form-label" for="riserStatus">وضعیت</label><select id="riserStatus" name="status" class="form-select"><option value="">همه وضعیت‌ها</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $statusLabels[$status] ?? $status }}</option>@endforeach</select></div>
                <div><label class="form-label" for="codeState">وضعیت کد</label><select id="codeState" name="code_state" class="form-select"><option value="">همه</option><option value="has_code" @selected(($filters['code_state'] ?? '') === 'has_code')>دارای کد</option><option value="no_code" @selected(($filters['code_state'] ?? '') === 'no_code')>بدون کد</option></select></div>
                <div><label class="form-label" for="riserSort">مرتب‌سازی</label><select id="riserSort" name="sort" class="form-select">@foreach($sortLabels as $value => $label)<option value="{{ $value }}" @selected(($filters['sort'] ?? 'updated_at') === $value)>{{ $label }}</option>@endforeach</select></div>
                <div><label class="form-label" for="riserDirection">ترتیب</label><select id="riserDirection" name="direction" class="form-select"><option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>نزولی</option><option value="asc" @selected(($filters['direction'] ?? '') === 'asc')>صعودی</option></select></div>
                <div class="filter-actions"><button class="btn btn-primary" type="submit"><i class="bi bi-search"></i> اعمال</button><a class="btn btn-outline-secondary" href="{{ route('riserIndex') }}">بازنشانی</a></div>
            </div>
            <input type="hidden" name="per_page" value="{{ $perPage }}">
        </form>
    </details>

    <section class="riser-panel table-panel">
        <div class="table-wrap"><table class="table table-hover riser-table mb-0">
            <thead><tr><th>شناسه</th><th>r_giscode</th><th>giscode</th><th>وضعیت</th><th>آخرین تغییر</th><th class="text-center">عملیات</th></tr></thead>
            <tbody>
            @forelse($risers as $riser)
                @php($status = (string) ($riser->status ?? ''))
                <tr>
                    <td data-label="شناسه"><strong>{{ $riser->id }}</strong></td>
                    <td data-label="r_giscode" class="code-cell">{{ $riser->r_giscode ?: '—' }}</td>
                    <td data-label="giscode" class="code-cell">{{ $riser->giscode ?: '—' }}</td>
                    <td data-label="وضعیت"><span class="status-chip status-{{ \Illuminate\Support\Str::slug($status ?: 'unknown') }}">{{ $statusLabels[$status] ?? ($status ?: 'نامشخص') }}</span></td>
                    <td data-label="آخرین تغییر">{{ optional($riser->updated_at)->format('Y/m/d H:i') ?: '—' }}</td>
                    <td data-label="عملیات" class="text-center"><a class="btn btn-sm btn-primary" href="{{ route('riser.show', ['id' => $riser->id, 'return_to' => url()->full()]) }}"><i class="bi bi-eye"></i> جزئیات و نقشه</a></td>
                </tr>
            @empty
                <tr><td colspan="6"><div class="empty-state"><i class="bi bi-search"></i><strong>علمک منطبق با فیلترها پیدا نشد</strong><div class="mt-2">فیلترها را بازنشانی کنید یا عبارت جست‌وجو را تغییر دهید.</div></div></td></tr>
            @endforelse
            </tbody>
        </table></div>
        <div class="pagination-wrap">
            <div class="small">نمایش {{ $risers->firstItem() ?? 0 }} تا {{ $risers->lastItem() ?? 0 }} از {{ number_format($risers->total()) }}</div>
            <div class="d-flex align-items-center gap-2">
                <form method="GET" action="{{ route('riserIndex') }}" class="d-flex align-items-center gap-2">
                    @foreach(request()->except('per_page', 'page') as $key => $value)
                        @if(is_array($value)) @foreach($value as $item)<input type="hidden" name="{{ $key }}[]" value="{{ $item }}">@endforeach @else <input type="hidden" name="{{ $key }}" value="{{ $value }}"> @endif
                    @endforeach
                    <label class="small" for="perPage">ردیف</label><select id="perPage" name="per_page" class="form-select form-select-sm" onchange="this.form.submit()">@foreach([25, 50, 100] as $option)<option value="{{ $option }}" @selected($perPage === $option)>{{ $option }}</option>@endforeach</select>
                </form>
                {{ $risers->onEachSide(1)->links() }}
            </div>
        </div>
    </section>
</div>

<script>
(() => { const input = document.getElementById('riserSearch'); const form = document.getElementById('riserFilters'); let timer; input?.addEventListener('input', () => { clearTimeout(timer); const value = input.value.trim(); if (value.length !== 0 && value.length < 3) return; timer = setTimeout(() => form.submit(), 500); }); })();
</script>
@endsection
