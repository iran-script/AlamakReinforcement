@extends('layouts.app')

@section('title', 'پرونده‌های تعمیر')

@section('content')
@php($canManageCases = auth()->user()->hasRole('superadmin') || (auth()->user()->hasRole('boss') && auth()->user()->can('operation.edit')))
@php($canReviewCases = auth()->user()->hasRole('boss') && auth()->user()->can('operation.approve'))
<div class="container-fluid py-3" dir="rtl">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div><h1 class="h4 mb-1">پرونده‌های تعمیر</h1><p class="text-muted mb-0">پرونده‌ها و گزارش‌های گروه کاری مجاز شما</p></div>
        <a href="{{ route('map') }}" class="btn btn-outline-primary">بازگشت به نقشه</a>
    </div>

    <form method="GET" class="card card-body mb-3">
        <div class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label" for="status">وضعیت پرونده</label>
                <select class="form-select" name="status" id="status"><option value="">همه</option>
                    @foreach(['open' => 'باز', 'in_progress' => 'در حال انجام', 'submitted' => 'ارسال‌شده برای بررسی', 'returned' => 'برگشت‌خورده', 'completed' => 'تکمیل‌شده'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-primary">اعمال فیلتر</button> <a class="btn btn-outline-secondary" href="{{ route('maintenance-cases.index') }}">پاک‌کردن</a></div>
        </div>
    </form>

    <div id="caseMessage" class="alert d-none" role="status"></div>
    <div class="card"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>علمک</th><th>وضعیت</th><th>اولویت</th><th>ناظر ثبت‌کننده</th><th>گروه کاری</th><th>تاریخ شناسایی</th><th>گزارش تعمیر</th>@if($canManageCases || $canReviewCases)<th>اقدامات</th>@endif</tr></thead>
            <tbody>
            @forelse($cases as $case)
                <tr>
                    <td><strong>{{ $case->riser?->r_giscode ?? $case->riser?->giscode ?? '—' }}</strong><div class="small text-muted">پرونده #{{ $case->id }}</div></td>
                    <td>{{ ['open' => 'باز', 'in_progress' => 'در حال انجام', 'submitted' => 'ارسال‌شده برای بررسی', 'returned' => 'برگشت‌خورده', 'completed' => 'تکمیل‌شده'][$case->status] ?? $case->status }}</td>
                    <td>{{ $case->priority }}</td>
                    <td>{{ $case->reporter?->name ?? '—' }}</td>
                    <td>{{ $case->assignedGroup?->name ?? '—' }}</td>
                    <td>{{ $case->identified_at?->format('Y-m-d') }}</td>
                    <td>@forelse($case->operations as $operation){{ $operation->operationCategory?->title }} · {{ $operation->operation_date }} · {{ $operation->status }}<br>@empty — @endforelse</td>
                    @if($canManageCases || $canReviewCases)
                    <td class="text-nowrap">
                        @if($canReviewCases && $case->status === 'submitted' && $case->operations->isNotEmpty())
                            @foreach($case->operations as $operation)
                                <div class="small text-muted">{{ $operation->operationCategory?->title }} · {{ $operation->operation_date }} · {{ $operation->status }}</div>
                                @if($operation->status === 'submitOperation')
                                <button class="btn btn-sm btn-success js-case-review" data-url="{{ route('maintenance-cases.review', $case) }}" data-operation-id="{{ $operation->id }}" data-decision="approved">&#1578;&#1575;&#1740;&#1740;&#1583; #{{ $operation->id }}</button>
                                <button class="btn btn-sm btn-outline-danger js-case-review" data-url="{{ route('maintenance-cases.review', $case) }}" data-operation-id="{{ $operation->id }}" data-decision="returned">&#1576;&#1575;&#1586;&#1711;&#1588;&#1578; #{{ $operation->id }}</button>
                                @endif
                            @endforeach
                        @endif
                        @if($canManageCases)
                            <details class="mt-2"><summary class="btn btn-sm btn-outline-primary d-inline-block">ویرایش</summary>
                                <form class="js-case-edit mt-2" data-url="{{ route('maintenance-cases.update', $case) }}">
                                    <label class="form-label small">اولویت</label>
                                    <select class="form-select form-select-sm mb-2" name="priority">
                                        @foreach(['کم', 'متوسط', 'زیاد', 'فوری'] as $priority)<option value="{{ $priority }}" @selected($case->priority === $priority)>{{ $priority }}</option>@endforeach
                                    </select>
                                    <label class="form-label small">توضیحات پرونده</label>
                                    <textarea class="form-control form-control-sm mb-2" name="description" rows="2">{{ $case->description }}</textarea>
                                    @if($case->operations->count() === 1)
                                        <label class="form-label small">تاریخ واقعی تعمیر</label>
                                        <input class="form-control form-control-sm mb-2" type="date" name="operation_date" value="{{ $case->operation->operation_date }}">
                                    @endif
                                    <button class="btn btn-sm btn-primary">ذخیره</button>
                                </form>
                            </details>
                        @endif
                    </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-5">پرونده‌ای در محدوده مجاز پیدا نشد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div><div class="card-body">{{ $cases->links() }}</div></div>
</div>
<script>
(() => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const message = document.getElementById('caseMessage');
    const showMessage = (text, ok = true) => { message.textContent = text; message.className = `alert ${ok ? 'alert-success' : 'alert-danger'}`; };
    document.querySelectorAll('.js-case-review').forEach(button => button.addEventListener('click', async () => {
        const comment = button.dataset.decision === 'returned' ? window.prompt('دلیل بازگشت برای اصلاح را وارد کنید:') : '';
        if (button.dataset.decision === 'returned' && !comment) return;
        const response = await fetch(button.dataset.url, {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token}, body: JSON.stringify({decision: button.dataset.decision, operation_id: Number(button.dataset.operationId), comment})});
        if (response.ok) window.location.reload(); else showMessage('ثبت تصمیم انجام نشد. دسترسی و وضعیت پرونده را بررسی کنید.', false);
    }));
    document.querySelectorAll('.js-case-edit').forEach(form => form.addEventListener('submit', async event => {
        event.preventDefault();
        const response = await fetch(form.dataset.url, {method: 'PUT', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token}, body: JSON.stringify(Object.fromEntries(new FormData(form)))});
        if (response.ok) window.location.reload(); else showMessage('ذخیره تغییرات انجام نشد. دسترسی و اطلاعات را بررسی کنید.', false);
    }));
})();
</script>
@endsection
