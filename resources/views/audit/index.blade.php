@extends('layouts.app')

@section('title', 'تاریخچه رویدادها')

@section('content')
<div class="container-fluid py-3" dir="rtl">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div><h1 class="h4 mb-1">تاریخچه رویدادها</h1><p class="text-muted mb-0">ثبت append-only تغییرات سامانه</p></div>
    </div>
    <form method="GET" class="card card-body mb-3">
        <div class="row g-2">
            <div class="col-md-2"><label class="form-label">از تاریخ</label><input class="form-control" type="date" name="from" value="{{ $filters['from'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label">تا تاریخ</label><input class="form-control" type="date" name="to" value="{{ $filters['to'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label">کاربر</label><input class="form-control" name="actor" value="{{ $filters['actor'] ?? '' }}" placeholder="نام یا Username"></div>
            <div class="col-md-2"><label class="form-label">کد علمک</label><input class="form-control" name="riser" value="{{ $filters['riser'] ?? '' }}" placeholder="GISCode"></div>
            <div class="col-md-2"><label class="form-label">شناسه Zone</label><input class="form-control" type="number" name="zone_id" value="{{ $filters['zone_id'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label">رویداد</label><input class="form-control" name="event" maxlength="100" value="{{ $filters['event'] ?? '' }}"></div>
            <div class="col-12"><button class="btn btn-primary">اعمال فیلتر</button><a class="btn btn-outline-secondary" href="{{ route('audit.index') }}">پاک‌کردن</a></div>
        </div>
    </form>
    <div class="table-responsive card">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>زمان</th><th>کاربر / نقش</th><th>رویداد</th><th>Zone</th><th>علمک</th><th>عملیات</th><th>جزئیات تغییر</th><th>Request ID</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $log->actor?->name ?? 'سیستم' }}<div class="small text-muted">{{ $log->actor_role }}</div></td>
                    <td><code>{{ $log->event }}</code></td>
                    <td>{{ $log->zone?->name ?? '—' }}</td>
                    <td>{{ $log->riser?->r_giscode ?? '—' }}</td>
                    <td>{{ $log->operation_id ?? '—' }}</td>
                    <td><details><summary>نمایش</summary><pre class="small mb-0">{{ json_encode(['before' => $log->before, 'after' => $log->after, 'metadata' => $log->metadata], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></details></td>
                    <td><small>{{ $log->request_id }}</small></td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-4">رویدادی مطابق فیلترها نیست.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $logs->links() }}</div>
</div>
@endsection
