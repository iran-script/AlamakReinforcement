@extends('layouts.app')

@section('title', 'بررسی پرونده تعمیرات #'.$case->id)

@section('content')
<style>
    .boss-review-page{direction:rtl;padding:20px 0 55px}.boss-review-shell{width:min(1540px,calc(100% - 28px));margin:auto}.boss-review-head{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap;padding:18px 20px;border-radius:18px;background:#fff;border:1px solid #e4e8f0;box-shadow:0 8px 26px #1018280b}.boss-review-head h1{margin:0;color:#17233f;font-size:clamp(21px,3vw,30px)}.boss-review-actions{display:flex;flex-wrap:wrap;gap:8px}.boss-review-actions .btn{min-height:46px}.final-review-card{margin-top:18px;padding:18px;border:1px solid #cbd8f4;border-radius:18px;background:linear-gradient(135deg,#f5f8ff,#fff);box-shadow:0 8px 26px #1018280b}.final-review-card h2{font-size:18px;color:#17233f;margin:0 0 5px}.final-review-buttons{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}.final-review-buttons .btn{min-height:48px}.review-dialog{width:min(560px,calc(100% - 24px));border:0;border-radius:16px;padding:0;box-shadow:0 22px 70px #10182840}.review-dialog::backdrop{background:#0b173566}.review-dialog-body{padding:20px}.review-dialog textarea{min-height:130px}.review-dialog-actions{display:flex;justify-content:flex-start;gap:8px;margin-top:14px}.review-dialog-actions .btn{min-height:44px}
</style>
<div class="boss-review-page">
    <div class="boss-review-shell">
        <header class="boss-review-head"><div><small class="text-muted">بررسی فقط‌خواندنی رئیس سازمان</small><h1>بررسی پرونده تعمیرات #{{ $case->id }}</h1><div class="text-muted small mt-1">علمک: {{ $case->riser?->r_giscode ?? $case->riser?->giscode ?? $case->riser_id }} · گروه: {{ $case->assignedGroup?->name ?? '—' }}</div></div><div class="boss-review-actions"><a class="btn btn-outline-secondary" href="{{ route('boss.maintenance-cases.index') }}"><i class="bi bi-arrow-right"></i>صف تأییدها</a><a class="btn btn-outline-primary" href="{{ route('riser.show', ['id' => $case->riser_id]) }}">جزئیات علمک</a></div></header>
        @if($canFinalReview)
            <section class="final-review-card"><h2>تصمیم نهایی تعمیرات</h2><p class="text-muted mb-0">پس از تأیید، پرونده تکمیل و عملیات برای کاربران عادی قفل می‌شوند. برگشت پرونده نیازمند دلیل است.</p><div class="final-review-buttons"><button type="button" class="btn btn-success" id="final-approve"><i class="bi bi-check2-circle"></i>تأیید نهایی تعمیرات</button><button type="button" class="btn btn-outline-danger" id="final-return"><i class="bi bi-arrow-return-right"></i>برگشت برای اصلاح</button></div></section>
        @else
            <div class="alert alert-info mt-3">این پرونده در وضعیت «{{ $case->status }}» قرار دارد و اکنون قابل تأیید نهایی نیست.</div>
        @endif
        @include('components.maintenance-case.overview', ['maintenanceCase' => $case, 'historicalCases' => collect(), 'timeline' => $timeline, 'canEditOperations' => false, 'canSubmitOperations' => false, 'canReview' => false, 'canEditPhotos' => false, 'showOperationReview' => false])
    </div>
</div>
@if($canFinalReview)
<dialog class="review-dialog" id="return-dialog"><form method="dialog" class="review-dialog-body" id="return-form"><h2 class="h5">دلیل برگشت برای اصلاح</h2><p class="text-muted small">این توضیح برای گروه تعمیراتی و تاریخچه پرونده ثبت می‌شود.</p><textarea class="form-control" id="return-comment" maxlength="5000" required placeholder="دلیل اصلاح را وارد کنید"></textarea><div class="review-dialog-actions"><button class="btn btn-danger" value="submit">ثبت برگشت</button><button class="btn btn-outline-secondary" value="cancel">انصراف</button></div></form></dialog>
@endif
@endsection

@push('scripts')
@if($canFinalReview)
<script>
(() => {
    const url = @json(route('boss.maintenance-cases.review', $case));
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const send = async (decision, comment = '') => {
        const buttons = document.querySelectorAll('#final-approve,#final-return'); buttons.forEach(button => { button.disabled = true; });
        try {
            const response = await fetch(url, {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token}, body:JSON.stringify({decision, comment})});
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message || 'ثبت تصمیم انجام نشد.');
            window.location.href = @json(route('boss.maintenance-cases.index')) + '?status=' + (decision === 'approved' ? 'completed' : 'returned');
        } catch (error) { window.alert(error.message || 'خطا در ثبت تصمیم.'); buttons.forEach(button => { button.disabled = false; }); }
    };
    document.getElementById('final-approve')?.addEventListener('click', () => { if (window.confirm('تأیید نهایی تعمیرات و تکمیل پرونده انجام شود؟')) send('approved'); });
    const dialog = document.getElementById('return-dialog');
    document.getElementById('final-return')?.addEventListener('click', () => dialog?.showModal());
    document.getElementById('return-form')?.addEventListener('submit', event => { event.preventDefault(); const comment=document.getElementById('return-comment')?.value.trim() || ''; if(!comment){document.getElementById('return-comment')?.focus();return;} dialog.close(); send('returned', comment); });
})();
</script>
@endif
@endpush
