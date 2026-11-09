@extends('layouts.app')

@section('title', 'پرونده‌های تعمیرات')

@section('content')
@php
    $statusMeta = [
        'open' => ['label' => 'پیش‌نویس', 'class' => 'status-draft', 'icon' => 'bi-pencil-square'],
        'in_progress' => ['label' => 'در حال ثبت', 'class' => 'status-progress', 'icon' => 'bi-hourglass-split'],
        'submitted' => ['label' => 'ارسال‌شده / منتظر تأیید', 'class' => 'status-submitted', 'icon' => 'bi-send-check'],
        'returned' => ['label' => 'برگشت برای اصلاح', 'class' => 'status-returned', 'icon' => 'bi-arrow-counterclockwise'],
        'completed' => ['label' => 'تأیید و تکمیل‌شده', 'class' => 'status-completed', 'icon' => 'bi-check-circle'],
    ];
    $selectedStatus = $filters['status'] ?? null;
@endphp

<style>
    .mc-index { --mc-ink: #172033; --mc-muted: #687386; --mc-border: #e4e9f0; color: var(--mc-ink); }
    .mc-index .mc-hero { background: linear-gradient(135deg, #f7fbff 0%, #fff 58%, #f8f6ff 100%); border: 1px solid var(--mc-border); border-radius: 1.25rem; padding: clamp(1.15rem, 2.5vw, 2rem); }
    .mc-index .mc-eyebrow { color: #6073a8; font-size: .78rem; font-weight: 800; letter-spacing: .08em; }
    .mc-index .mc-title { font-size: clamp(1.55rem, 3vw, 2.2rem); font-weight: 800; margin: .3rem 0 .45rem; }
    .mc-index .mc-subtitle { color: var(--mc-muted); margin: 0; }
    .mc-index .mc-stat { position: relative; overflow: hidden; border: 1px solid var(--mc-border); border-radius: 1rem; background: #fff; min-height: 116px; padding: 1rem; transition: transform .15s ease, box-shadow .15s ease; }
    .mc-index .mc-stat:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1.5rem rgba(34, 52, 84, .08); }
    .mc-index .mc-stat::before { content: ''; position: absolute; inset: 0 auto 0 0; width: 4px; background: var(--mc-accent); }
    .mc-index .mc-stat-icon { color: var(--mc-accent); font-size: 1.25rem; }
    .mc-index .mc-stat-value { display: block; font-size: 1.65rem; font-weight: 800; line-height: 1.2; margin-top: .55rem; }
    .mc-index .mc-stat-label { color: var(--mc-muted); font-size: .86rem; }
    .mc-index .mc-stat-draft { --mc-accent: #9a6b1f; } .mc-index .mc-stat-submitted { --mc-accent: #2563a6; }
    .mc-index .mc-stat-returned { --mc-accent: #bd5a27; } .mc-index .mc-stat-completed { --mc-accent: #25805a; }
    .mc-index .mc-filter { border: 1px solid var(--mc-border); border-radius: 1rem; background: #fff; padding: 1rem; }
    .mc-index .form-control, .mc-index .form-select { min-height: 44px; border-radius: .7rem; }
    .mc-index .mc-list { border: 1px solid var(--mc-border); border-radius: 1rem; overflow: hidden; background: #fff; }
    .mc-index .mc-list-head { padding: 1rem 1.15rem; border-bottom: 1px solid var(--mc-border); }
    .mc-index .mc-table th { color: var(--mc-muted); font-size: .8rem; font-weight: 700; white-space: nowrap; }
    .mc-index .mc-table td, .mc-index .mc-table th { padding: 1rem .85rem; vertical-align: middle; }
    .mc-index .mc-table tbody tr { border-right: 4px solid transparent; }
    .mc-index .mc-table tbody tr.status-row-draft { border-right-color: #b88735; }
    .mc-index .mc-table tbody tr.status-row-submitted { border-right-color: #3981bd; }
    .mc-index .mc-table tbody tr.status-row-returned { border-right-color: #d97745; }
    .mc-index .mc-table tbody tr.status-row-completed { border-right-color: #36a269; }
    .mc-index .mc-card { border: 1px solid var(--mc-border); border-radius: 1rem; padding: 1rem; background: #fff; height: 100%; }
    .mc-index .mc-card .mc-card-code { font-size: 1.05rem; font-weight: 800; }
    .mc-index .mc-badge { display: inline-flex; align-items: center; gap: .35rem; min-height: 32px; padding: .35rem .65rem; border-radius: 999px; font-size: .78rem; font-weight: 700; }
    .mc-index .status-draft { color: #805c1d; background: #fff5dc; } .mc-index .status-progress { color: #765b20; background: #fff6e6; }
    .mc-index .status-submitted { color: #1e5c93; background: #e8f3ff; } .mc-index .status-returned { color: #a44820; background: #fff0e8; }
    .mc-index .status-completed { color: #1f704d; background: #e7f7ef; }
    .mc-index .mc-empty { padding: 3rem 1rem; text-align: center; color: var(--mc-muted); }
    .mc-index .mc-touch { min-height: 44px; display: inline-flex; align-items: center; justify-content: center; }
    @media (max-width: 991.98px) { .mc-index .mc-table-wrap { display: none; } }
    @media (min-width: 992px) { .mc-index .mc-card-list { display: none; } }
</style>

<main class="container-fluid py-3 py-lg-4 mc-index" dir="rtl">
    <section class="mc-hero mb-3 mb-lg-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
            <div><div class="mc-eyebrow">MAINTENANCE CASES</div><h1 class="mc-title">پرونده‌های تعمیرات</h1><p class="mc-subtitle">پیگیری وضعیت پرونده‌ها، عملیات ثبت‌شده و مسیر اصلاح تعمیرات.</p></div>
            <a href="{{ route('map') }}" class="btn btn-outline-primary mc-touch px-3"><i class="bi bi-map ms-1"></i>بازگشت به نقشه</a>
        </div>
    </section>

    <section class="row g-3 mb-3 mb-lg-4" aria-label="آمار پرونده‌ها">
        @foreach([
            ['key' => 'open', 'label' => 'پیش‌نویس / در حال ثبت', 'class' => 'mc-stat-draft', 'icon' => 'bi-pencil-square', 'value' => (int) (($statusCounts['open'] ?? 0) + ($statusCounts['in_progress'] ?? 0))],
            ['key' => 'submitted', 'label' => 'ارسال‌شده برای تأیید', 'class' => 'mc-stat-submitted', 'icon' => 'bi-send-check', 'value' => (int) ($statusCounts['submitted'] ?? 0)],
            ['key' => 'returned', 'label' => 'برگشت برای اصلاح', 'class' => 'mc-stat-returned', 'icon' => 'bi-arrow-counterclockwise', 'value' => (int) ($statusCounts['returned'] ?? 0)],
            ['key' => 'completed', 'label' => 'تأیید و تکمیل‌شده', 'class' => 'mc-stat-completed', 'icon' => 'bi-check-circle', 'value' => (int) ($statusCounts['completed'] ?? 0)],
        ] as $stat)
            <div class="col-6 col-xl-3"><a class="text-decoration-none text-reset" href="{{ route('maintenance-cases.index', ['status' => $stat['key']]) }}"><div class="mc-stat {{ $stat['class'] }}"><i class="bi {{ $stat['icon'] }} mc-stat-icon"></i><span class="mc-stat-value">{{ number_format($stat['value']) }}</span><span class="mc-stat-label">{{ $stat['label'] }}</span></div></a></div>
        @endforeach
    </section>

    <section class="mc-filter mb-3 mb-lg-4" aria-label="فیلتر پرونده‌ها">
        <form method="GET" action="{{ route('maintenance-cases.index') }}">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-lg-3"><label class="form-label" for="caseSearch">جست‌وجوی کد علمک یا شماره پرونده</label><input id="caseSearch" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" type="search" placeholder="مثلاً 56013 یا 21"></div>
                <div class="col-6 col-lg-2"><label class="form-label" for="caseStatus">وضعیت</label><select id="caseStatus" name="status" class="form-select"><option value="">همه وضعیت‌ها</option>@foreach($statusMeta as $value => $meta)<option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $meta['label'] }}</option>@endforeach</select></div>
                @if(isset($organizations))<div class="col-6 col-lg-2"><label class="form-label" for="caseOrganization">سازمان</label><select id="caseOrganization" name="organization" class="form-select"><option value="">همه سازمان‌ها</option>@foreach($organizations as $organization)<option value="{{ $organization->id }}" @selected((string) ($filters['organization'] ?? '') === (string) $organization->id)>{{ $organization->name }}</option>@endforeach</select></div>@endif
                @if(isset($zones))<div class="col-6 col-lg-2"><label class="form-label" for="caseZone">Zone</label><select id="caseZone" name="zone" class="form-select"><option value="">همه Zoneها</option>@foreach($zones as $zone)<option value="{{ $zone->id }}" @selected((string) ($filters['zone'] ?? '') === (string) $zone->id)>{{ $zone->name }}{{ $zone->organization?->name ? ' · '.$zone->organization->name : '' }}</option>@endforeach</select></div>@endif
                <div class="col-6 col-lg-2"><label class="form-label" for="caseGroup">گروه تعمیراتی</label><select id="caseGroup" name="group" class="form-select"><option value="">همه گروه‌های مجاز</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected((string) ($filters['group'] ?? '') === (string) $group->id)>{{ $group->name }}</option>@endforeach</select></div>
                <div class="col-6 col-lg-2"><label class="form-label" for="caseFrom">از تاریخ</label><input id="caseFrom" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control" type="date"></div>
                <div class="col-6 col-lg-2"><label class="form-label" for="caseTo">تا تاریخ</label><input id="caseTo" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control" type="date"></div>
                <div class="col-12 col-lg-1 d-flex gap-2"><button type="submit" class="btn btn-primary mc-touch flex-grow-1" aria-label="اعمال فیلتر"><i class="bi bi-search"></i></button><a href="{{ route('maintenance-cases.index') }}" class="btn btn-outline-secondary mc-touch flex-grow-1" aria-label="پاک کردن فیلترها"><i class="bi bi-x-lg"></i></a></div>
            </div>
        </form>
    </section>

    <section class="mc-list">
        <div class="mc-list-head d-flex flex-wrap justify-content-between align-items-center gap-2"><div><h2 class="h5 mb-1">فهرست پرونده‌ها</h2><span class="small text-muted">{{ number_format($cases->total()) }} پرونده در محدوده دسترسی شما</span></div>@if($selectedStatus && isset($statusMeta[$selectedStatus]))<span class="mc-badge {{ $statusMeta[$selectedStatus]['class'] }}"><i class="bi {{ $statusMeta[$selectedStatus]['icon'] }}"></i>{{ $statusMeta[$selectedStatus]['label'] }}</span>@endif</div>
        <div class="mc-table-wrap table-responsive"><table class="table mc-table table-hover mb-0"><thead><tr><th>شماره پرونده</th><th>کد علمک</th><th>گروه تعمیراتی</th><th>آخرین تغییر</th><th>عملیات فعال</th><th>وضعیت</th><th class="text-start">اقدام</th></tr></thead><tbody>
            @forelse($cases as $case)
                @php($meta = $statusMeta[$case->status] ?? ['label' => 'وضعیت نامشخص', 'class' => 'status-draft', 'icon' => 'bi-question-circle'])
                @php($rowStatus = in_array($case->status, ['open', 'in_progress'], true) ? 'draft' : str_replace('status-', '', $meta['class']))
                <tr class="status-row-{{ $rowStatus }}"><td><strong>#{{ $case->id }}</strong><div class="small text-muted">{{ $case->identified_at?->format('Y/m/d') ?? '—' }}</div></td><td><strong>{{ $case->riser?->r_giscode ?? $case->riser?->giscode ?? '—' }}</strong></td><td>{{ $case->assignedGroup?->name ?? '—' }}</td><td>{{ $case->updated_at?->format('Y/m/d H:i') ?? '—' }}</td><td><span class="fw-semibold">{{ number_format((int) $case->operations_count) }}</span></td><td><span class="mc-badge {{ $meta['class'] }}"><i class="bi {{ $meta['icon'] }}"></i>{{ $meta['label'] }}</span></td><td class="text-start"><a class="btn btn-sm btn-primary mc-touch px-3" href="{{ route('riser.show', ['id' => $case->riser_id]) }}">مشاهده پرونده</a></td></tr>
            @empty
                <tr><td colspan="7"><div class="mc-empty"><i class="bi bi-folder2-open fs-2 d-block mb-2"></i><div class="fw-semibold">پرونده‌ای در این محدوده پیدا نشد.</div><div class="small mt-1">فیلترها را تغییر دهید یا بعداً دوباره بررسی کنید.</div></div></td></tr>
            @endforelse
        </tbody></table></div>

        <div class="mc-card-list p-3"><div class="row g-3">
            @forelse($cases as $case)
                @php($meta = $statusMeta[$case->status] ?? ['label' => 'وضعیت نامشخص', 'class' => 'status-draft', 'icon' => 'bi-question-circle'])
                <div class="col-12 col-md-6"><article class="mc-card"><div class="d-flex justify-content-between align-items-start gap-2 mb-3"><div><div class="mc-card-code">پرونده #{{ $case->id }}</div><div class="small text-muted">علمک {{ $case->riser?->r_giscode ?? $case->riser?->giscode ?? '—' }}</div></div><span class="mc-badge {{ $meta['class'] }}"><i class="bi {{ $meta['icon'] }}"></i>{{ $meta['label'] }}</span></div><dl class="row small mb-3"><dt class="col-5 text-muted">گروه</dt><dd class="col-7 mb-2">{{ $case->assignedGroup?->name ?? '—' }}</dd><dt class="col-5 text-muted">آخرین تغییر</dt><dd class="col-7 mb-2">{{ $case->updated_at?->format('Y/m/d H:i') ?? '—' }}</dd><dt class="col-5 text-muted">عملیات فعال</dt><dd class="col-7 mb-0">{{ number_format((int) $case->operations_count) }}</dd></dl><a class="btn btn-primary w-100 mc-touch" href="{{ route('riser.show', ['id' => $case->riser_id]) }}"><i class="bi bi-eye ms-1"></i>مشاهده پرونده</a></article></div>
            @empty
                <div class="col-12"><div class="mc-empty"><i class="bi bi-folder2-open fs-2 d-block mb-2"></i><div class="fw-semibold">پرونده‌ای در این محدوده پیدا نشد.</div></div></div>
            @endforelse
        </div></div>

        @if($cases->hasPages())<div class="p-3 border-top">{{ $cases->links() }}</div>@endif
    </section>
</main>
@endsection
