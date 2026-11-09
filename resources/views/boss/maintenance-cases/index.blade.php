@extends('layouts.app')

@section('title', 'تأیید تعمیرات')

@section('content')
<style>
    .boss-queue{direction:rtl;padding:20px 0 55px}.boss-shell{width:min(1440px,calc(100% - 28px));margin:auto}.boss-hero{padding:24px;border-radius:20px;color:#fff;background:linear-gradient(135deg,#162a55,#2856a1)}.boss-hero h1{font-size:clamp(23px,3vw,34px);font-weight:850;margin:4px 0}.boss-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:16px}.boss-stat,.boss-card{background:#fff;border:1px solid #e4e8f0;border-radius:16px;box-shadow:0 8px 26px #1018280b}.boss-stat{padding:16px}.boss-stat strong{display:block;font-size:27px;color:#17233f}.boss-stat span{color:#667085;font-size:12px}.boss-card{padding:18px;margin-top:18px}.boss-filter-grid{display:grid;grid-template-columns:2fr repeat(4,1fr) auto;gap:10px;align-items:end}.boss-filter-grid .form-control,.boss-filter-grid .form-select,.boss-filter-grid .btn{min-height:46px}.boss-case-list{display:grid;gap:12px;margin-top:16px}.boss-case{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;padding:16px;border:1px solid #e4e8f0;border-radius:14px}.boss-case h2{font-size:16px;color:#17233f;margin:0 0 5px}.boss-case-meta{display:flex;flex-wrap:wrap;gap:8px;color:#667085;font-size:12px}.boss-case-action{display:flex;align-items:center}.boss-case-action .btn{min-height:46px}.boss-badge{display:inline-flex;align-items:center;min-height:28px;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:750;background:#eef1f5;color:#5e6878}.boss-badge.pending{background:#fff4df;color:#a86c05}.boss-badge.done{background:#e7f7f0;color:#138a68}.boss-badge.returned{background:#fff0f1;color:#c83b43}@media(max-width:900px){.boss-filter-grid{grid-template-columns:repeat(2,1fr)}.boss-filter-grid .query{grid-column:1/-1}.boss-case{grid-template-columns:1fr}.boss-case-action .btn{width:100%}}@media(max-width:560px){.boss-queue{padding:12px 0 40px}.boss-shell{width:calc(100% - 20px)}.boss-stats{grid-template-columns:1fr}.boss-filter-grid{grid-template-columns:1fr}}
</style>
<div class="boss-queue">
    <div class="boss-shell">
        <header class="boss-hero d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div><small class="text-white-50">پنل رئیس سازمان</small><h1>تأیید تعمیرات</h1><p class="mb-0 text-white-50">پرونده‌های ارسال‌شده در محدوده گروه‌های مدیریتی شما</p></div>
            <a class="btn btn-light" href="{{ route('dashboard') }}" style="min-height:46px"><i class="bi bi-arrow-right"></i>بازگشت به داشبورد</a>
        </header>
        <div class="boss-stats"><div class="boss-stat"><strong>{{ number_format($stats['submitted']) }}</strong><span>در انتظار تأیید</span></div><div class="boss-stat"><strong>{{ number_format($stats['completed']) }}</strong><span>تأییدشده</span></div><div class="boss-stat"><strong>{{ number_format($stats['returned']) }}</strong><span>برگشت‌خورده</span></div></div>
        <section class="boss-card">
            <form method="get" class="boss-filter-grid">
                <div class="query"><label class="form-label" for="approval-q">جست‌وجو</label><input id="approval-q" class="form-control" name="q" value="{{ request('q') }}" placeholder="کد علمک یا شماره پرونده"></div>
                <div><label class="form-label" for="approval-group">گروه</label><select id="approval-group" class="form-select" name="group"><option value="">همه گروه‌ها</option>@foreach($groups as $group)<option value="{{ $group->id }}" @selected((string) request('group') === (string) $group->id)>{{ $group->name }}</option>@endforeach</select></div>
                <div><label class="form-label" for="approval-status">وضعیت</label><select id="approval-status" class="form-select" name="status"><option value="">همه</option><option value="submitted" @selected(request('status') === 'submitted' || !request('status'))>در انتظار تأیید</option><option value="returned" @selected(request('status') === 'returned')>برگشت‌خورده</option><option value="completed" @selected(request('status') === 'completed')>تکمیل‌شده</option></select></div>
                <div><label class="form-label" for="approval-from">از تاریخ ارسال</label><input id="approval-from" class="form-control" type="date" name="from" value="{{ request('from') }}"></div>
                <div><label class="form-label" for="approval-to">تا تاریخ ارسال</label><input id="approval-to" class="form-control" type="date" name="to" value="{{ request('to') }}"></div>
                <button class="btn btn-primary" type="submit">اعمال فیلتر</button>
            </form>
            <div class="boss-case-list">
                @forelse($cases as $case)
                    @php($caseStatus = ['submitted' => 'در انتظار تأیید', 'returned' => 'برگشت‌خورده', 'completed' => 'تأییدشده'][$case->status] ?? $case->status)
                    <article class="boss-case">
                        <div><div class="d-flex flex-wrap align-items-center gap-2"><h2>پرونده #{{ $case->id }}</h2><span class="boss-badge {{ $case->status === 'submitted' ? 'pending' : ($case->status === 'completed' ? 'done' : 'returned') }}">{{ $caseStatus }}</span></div><div class="boss-case-meta"><span>علمک: {{ $case->riser?->r_giscode ?? $case->riser?->giscode ?? $case->riser_id }}</span><span>گروه: {{ $case->assignedGroup?->name ?? '—' }}</span><span>ارسال: {{ $case->submitted_at?->format('Y/m/d H:i') ?? '—' }}</span><span>{{ $case->operations->count() }} عملیات</span><span>تصاویر: {{ $case->photos->whereNull('operation_id')->count() }}/۲</span></div></div>
                        <div class="boss-case-action"><a class="btn btn-outline-primary" href="{{ route('boss.maintenance-cases.show', $case) }}">بررسی پرونده <i class="bi bi-arrow-left"></i></a></div>
                    </article>
                @empty
                    <div class="text-center text-muted py-5">پرونده‌ای با این فیلتر پیدا نشد.</div>
                @endforelse
            </div>
            <div class="mt-3">{{ $cases->links() }}</div>
        </section>
    </div>
</div>
@endsection
