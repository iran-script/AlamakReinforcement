@extends('layouts.app')

@php
    $statusLabels = [
        'open' => 'باز',
        'in_progress' => 'در حال تعمیر',
        'submitted' => 'ارسال‌شده برای بررسی',
        'returned' => 'بازگشت برای اصلاح',
        'completed' => 'تکمیل‌شده',
    ];
    $faCount = fn ($value) => number_format((int) $value, 0, '.', ',');
    $operationStatusLabels = [
        'draft' => 'پیش‌نویس',
        'need_revision' => 'نیازمند اصلاح',
        'submitOperation' => 'در انتظار بررسی',
        'approved' => 'تأییدشده',
        'rejected' => 'ردشده',
    ];
    $status = $statusLabels[$case->status] ?? $case->status;
    $beforePhoto = $case->photos->whereNull('operation_id')->firstWhere('type', 'before');
    $afterPhoto = $case->photos->whereNull('operation_id')->firstWhere('type', 'after');
    $activeOperations = $case->operations;
    $workflowStage = match ($case->status) {
        'open' => 1,
        'in_progress' => 2,
        'submitted' => 4,
        'returned' => 2,
        'completed' => 5,
        default => 1,
    };
    $workflowSteps = ['نشانه‌گذاری', 'تعمیرات', 'ارسال برای تأیید', 'بررسی', 'تکمیل'];
@endphp

@section('title', 'پرونده تعمیرات علمک #'.$case->id)

@push('styles')
<style>
    .case-page{--case-ink:#17233f;--case-muted:#667085;--case-line:#e4e8f0;--case-soft:#f7f9fc;--case-primary:#315bb5;--case-primary-soft:#edf3ff;--case-success:#138a68;--case-success-soft:#e7f7f0;--case-warning:#a86c05;--case-warning-soft:#fff4df;--case-danger:#c83b43;--case-danger-soft:#fff0f1;padding:22px 0 80px}
    .case-shell{max-width:1540px;margin:auto}
    .case-hero{padding:24px;border-radius:22px;color:#fff;background:linear-gradient(135deg,#162a55,#2856a1);box-shadow:0 18px 42px #1d417a26}
    .case-eyebrow{color:#cbd8f4;font-size:12px}.case-hero h1{margin:4px 0 8px;font-size:clamp(23px,3vw,34px);font-weight:850}.case-meta{display:flex;flex-wrap:wrap;gap:8px;color:#e4ebfb;font-size:13px}.case-code{direction:ltr;font-family:var(--font-mono);padding:4px 9px;border:1px solid #ffffff3d;border-radius:8px;background:#ffffff18}
    .case-actions{display:flex;flex-wrap:wrap;gap:8px}.case-actions .btn{min-height:46px;display:inline-flex;align-items:center;gap:7px}
    .case-grid{display:grid;grid-template-columns:minmax(0,1.5fr) minmax(320px,.7fr);gap:18px;margin-top:18px}
    .case-card{background:var(--surface);border:1px solid var(--case-line);border-radius:18px;box-shadow:0 8px 26px #1018280b}.case-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:18px 20px;border-bottom:1px solid var(--case-line)}.case-card-head h2{margin:0;color:var(--case-ink);font-size:18px}.case-card-head p{margin:4px 0 0;color:var(--case-muted);font-size:12px}.case-card-body{padding:18px 20px}
    .summary-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px}.summary-item{min-height:108px;padding:15px;border:1px solid var(--case-line);border-radius:15px;background:linear-gradient(145deg,#fff,#fbfcff)}.summary-item strong{display:block;color:var(--case-ink);font-size:25px;line-height:1.3}.summary-item span{display:block;color:var(--case-muted);font-size:12px;margin-top:6px}.summary-item i{display:grid;place-items:center;width:32px;height:32px;margin-bottom:10px;border-radius:10px;color:var(--case-primary);background:var(--case-primary-soft);font-style:normal}.summary-item.success i{color:var(--case-success);background:var(--case-success-soft)}
    .workflow{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;position:relative}.workflow:before{content:"";position:absolute;top:18px;right:10%;left:10%;border-top:2px solid var(--case-line)}.workflow-step{position:relative;z-index:1;text-align:center;color:var(--case-muted);font-size:11px}.workflow-step b{display:grid;place-items:center;width:38px;height:38px;margin:0 auto 8px;border:4px solid #fff;border-radius:50%;background:#dfe4ec;color:#7b8495;font-size:13px;box-shadow:0 0 0 1px var(--case-line)}.workflow-step.done b{background:var(--case-success);color:#fff;box-shadow:0 0 0 1px var(--case-success)}.workflow-step.current{color:var(--case-primary);font-weight:800}.workflow-step.current b{background:var(--case-primary);color:#fff;box-shadow:0 0 0 4px #dbe6ff}.workflow-return{margin-top:14px;padding:10px 12px;border-radius:10px;color:var(--case-warning);background:var(--case-warning-soft);font-size:12px}
    .photo-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.photo-card{overflow:hidden;border:1px solid var(--case-line);border-radius:15px;background:var(--case-soft)}.photo-frame{display:grid;place-items:center;min-height:190px;background:linear-gradient(135deg,#eef2f8,#e4eaf3);color:var(--case-muted)}.photo-frame img{display:block;width:100%;height:230px;object-fit:cover}.photo-content{padding:13px}.photo-content h3{margin:0 0 4px;color:var(--case-ink);font-size:15px}.photo-content p{margin:0 0 10px;color:var(--case-muted);font-size:11px}.photo-content .btn{min-height:44px}
    .operation-list{display:grid;gap:12px}.operation-card{border:1px solid var(--case-line);border-radius:15px;background:#fff;overflow:hidden}.operation-card summary{display:flex;align-items:center;justify-content:space-between;gap:14px;min-height:76px;padding:14px 16px;cursor:pointer;list-style:none}.operation-card summary::-webkit-details-marker{display:none}.operation-card summary:focus-visible{outline:3px solid #b9ccfa;outline-offset:-3px}.operation-title{display:flex;align-items:center;gap:11px;min-width:0}.operation-number{display:grid;place-items:center;flex:0 0 auto;width:36px;height:36px;border-radius:11px;color:var(--case-primary);background:var(--case-primary-soft);font-weight:850}.operation-title strong{display:block;color:var(--case-ink);font-size:15px;overflow-wrap:anywhere}.operation-title small{display:block;color:var(--case-muted);font-size:11px;margin-top:2px}.operation-summary{display:flex;align-items:center;flex-wrap:wrap;justify-content:flex-end;gap:7px}.operation-body{padding:0 16px 16px;border-top:1px solid var(--case-line)}.operation-body p{margin:14px 0;color:#4d5870;font-size:13px}.operation-materials{display:flex;flex-wrap:wrap;gap:7px}.material-chip{display:inline-flex;align-items:center;gap:5px;padding:7px 10px;border-radius:9px;color:#33456a;background:var(--case-primary-soft);font-size:12px}.material-chip b{direction:ltr}.status-badge{display:inline-flex;align-items:center;min-height:28px;padding:4px 9px;border-radius:999px;font-size:11px;font-weight:750;background:#eef1f5;color:#5e6878}.status-badge.success{color:var(--case-success);background:var(--case-success-soft)}.status-badge.warning{color:var(--case-warning);background:var(--case-warning-soft)}.status-badge.danger{color:var(--case-danger);background:var(--case-danger-soft)}
    .material-table{width:100%;border-collapse:separate;border-spacing:0}.material-table th,.material-table td{padding:12px;border-bottom:1px solid var(--case-line);text-align:right;font-size:12px}.material-table th{color:var(--case-muted);font-weight:700;background:#fbfcfe}.material-table td{color:var(--case-ink)}.material-table tr:last-child td{border-bottom:0}.material-table .qty{direction:ltr;font-weight:800}
    .timeline{display:grid;gap:0}.timeline-item{position:relative;display:grid;grid-template-columns:22px minmax(0,1fr);gap:10px;padding-bottom:17px}.timeline-item:not(:last-child):before{content:"";position:absolute;right:10px;top:23px;bottom:0;border-right:1px dashed #cbd4e4}.timeline-dot{position:relative;z-index:1;width:20px;height:20px;border-radius:50%;background:var(--case-primary-soft);border:5px solid #fff;box-shadow:0 0 0 1px #9fb6e8}.timeline-content{padding:0 0 2px}.timeline-content strong{display:block;color:var(--case-ink);font-size:13px}.timeline-content small{color:var(--case-muted);font-size:11px}.timeline-content p{margin:3px 0 0;color:#566176;font-size:12px}
    .empty-state{padding:25px 16px;text-align:center;border:1px dashed #cfd6e3;border-radius:14px;color:var(--case-muted);background:var(--case-soft)}.empty-state i{display:block;margin-bottom:7px;font-size:27px;color:#9aa6ba;font-style:normal}.sticky-actions{position:sticky;bottom:14px;z-index:5;margin-top:18px;padding:11px 13px;border:1px solid #d5deed;border-radius:15px;background:#ffffffed;box-shadow:0 12px 30px #1b315d1c;backdrop-filter:blur(10px)}.sticky-actions .btn{min-height:46px}
    .case-message{display:none}.case-message.show{display:block}
    @media(max-width:1199.98px){.case-grid{grid-template-columns:minmax(0,1.15fr) minmax(300px,.85fr)}.summary-grid{grid-template-columns:repeat(3,1fr)}}
    @media(max-width:767.98px){.case-page{padding:12px 0 50px}.case-hero{padding:18px;border-radius:17px}.case-grid{grid-template-columns:1fr}.case-card-head,.case-card-body{padding:15px}.summary-grid{grid-template-columns:repeat(2,1fr)}.summary-item{min-height:96px;padding:12px}.summary-item strong{font-size:21px}.workflow{gap:2px}.workflow-step{font-size:9px}.workflow-step b{width:32px;height:32px;font-size:11px}.workflow:before{top:15px}.photo-grid{grid-template-columns:1fr}.photo-frame{min-height:150px}.photo-frame img{height:190px}.operation-card summary{align-items:flex-start;min-height:72px;padding:12px}.operation-summary{justify-content:flex-start}.material-table th,.material-table td{padding:9px 6px}.case-actions{width:100%}.case-actions .btn{flex:1;justify-content:center}}
</style>
@endpush

@section('content')
<div class="container-fluid case-page" dir="rtl">
    <div class="case-shell">
        <header class="case-hero">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="case-eyebrow">پرونده تعمیراتی</div>
                    <h1>پرونده #{{ $case->id }}</h1>
                    <div class="case-meta">
                        <span>علمک</span><span class="case-code">{{ $case->riser?->r_giscode ?? $case->riser?->giscode ?? '—' }}</span>
                        <span>·</span><span>{{ $status }}</span><span>·</span>
                        <span>{{ $case->identified_at?->format('Y/m/d H:i') ?? '—' }}</span>
                    </div>
                </div>
                <div class="case-actions">
                    <a class="btn btn-light" href="{{ url('/riser/'.$case->riser_id) }}"><i class="bi bi-arrow-right"></i> جزئیات علمک</a>
                    <a class="btn btn-outline-light" href="{{ route('map', ['riser_id' => $case->riser_id]) }}"><i class="bi bi-geo-alt"></i> نمایش روی نقشه</a>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-3 mt-3 small text-white-50">
                <span><i class="bi bi-people ms-1"></i>{{ $case->assignedGroup?->name ?? 'گروه مشخص نشده' }}</span>
                <span><i class="bi bi-person ms-1"></i>{{ $case->reporter?->name ?? 'کاربر مشخص نشده' }}</span>
                <span><i class="bi bi-clock-history ms-1"></i>آخرین ویرایش: {{ $case->lastEditor?->name ?? '—' }}</span>
            </div>
        </header>

        <div id="case-message" class="alert case-message mt-3" role="alert"></div>

        <section class="case-card mt-3" aria-labelledby="summary-title">
            <div class="case-card-head"><div><h2 id="summary-title">نمای کلی پرونده</h2><p>اطلاعات زنده پرونده و عملیات فعال</p></div><span class="status-badge {{ $case->status === 'completed' ? 'success' : ($case->status === 'returned' ? 'warning' : '') }}">{{ $status }}</span></div>
            <div class="case-card-body">
                <div class="summary-grid">
                    <div class="summary-item"><i class="bi bi-tools"></i><strong>{{ $faCount($activeOperations->count()) }}</strong><span>عملیات فعال</span></div>
                    <div class="summary-item"><i class="bi bi-box-seam"></i><strong>{{ $faCount($materialsSummary->count()) }}</strong><span>نوع مصالح</span></div>
                    <div class="summary-item {{ $beforePhoto ? 'success' : '' }}"><i class="bi bi-camera"></i><strong>{{ $beforePhoto ? 'ثبت' : '—' }}</strong><span>عکس قبل تعمیرات</span></div>
                    <div class="summary-item {{ $afterPhoto ? 'success' : '' }}"><i class="bi bi-camera2"></i><strong>{{ $afterPhoto ? 'ثبت' : '—' }}</strong><span>عکس بعد تعمیرات</span></div>
                    <div class="summary-item {{ $case->status === 'completed' ? 'success' : '' }}"><i class="bi bi-shield-check"></i><strong>{{ $case->status === 'completed' ? 'تأیید نهایی' : ($case->status === 'submitted' ? 'در بررسی' : 'در جریان') }}</strong><span>وضعیت تأیید</span></div>
                </div>
            </div>
        </section>

        <div class="case-grid">
            <main class="d-grid gap-3">
                <section class="case-card" aria-labelledby="workflow-title">
                    <div class="case-card-head"><div><h2 id="workflow-title">گردش‌کار پرونده</h2><p>مرحله جاری و وضعیت بازگشت برای اصلاح</p></div></div>
                    <div class="case-card-body">
                        <div class="workflow">
                            @foreach($workflowSteps as $index => $step)
                                <div class="workflow-step {{ $index + 1 < $workflowStage ? 'done' : '' }} {{ $index + 1 === $workflowStage ? 'current' : '' }}"><b>{{ $index + 1 < $workflowStage ? '✓' : $index + 1 }}</b><span>{{ $step }}</span></div>
                            @endforeach
                        </div>
                        @if($case->status === 'returned')<div class="workflow-return"><i class="bi bi-arrow-return-right ms-1"></i>این پرونده برای اصلاح برگشت خورده است؛ پس از اصلاح عملیات دوباره ارسال کنید.</div>@endif
                    </div>
                </section>

                <section class="case-card" aria-labelledby="photos-title">
                    <div class="case-card-head"><div><h2 id="photos-title">تصاویر پرونده</h2><p>یک تصویر قبل و یک تصویر بعد برای کل پرونده</p></div><span class="status-badge">operation_id = null</span></div>
                    <div class="case-card-body">
                        <div class="photo-grid">
                            @foreach([['before','قبل از تعمیرات','ثبت وضعیت اولیه'],['after','بعد از تعمیرات','ثبت نتیجه تعمیر']] as [$type,$title,$hint])
                                @php($photo = $type === 'before' ? $beforePhoto : $afterPhoto)
                                <article class="photo-card" data-photo-card="{{ $type }}">
                                    <div class="photo-frame">
                                        @if($photo)<a href="{{ route('maintenance-cases.photos.show', $photo) }}" target="_blank" rel="noopener"><img src="{{ route('maintenance-cases.photos.show', $photo) }}" alt="{{ $title }}"></a>@else<span><i class="bi bi-image fs-2 d-block text-center"></i>تصویری ثبت نشده است</span>@endif
                                    </div>
                                    <div class="photo-content"><h3>{{ $title }}</h3><p>{{ $hint }}</p>
                                        @if($canEditPhotos)
                                            <label class="btn btn-outline-primary w-100"><i class="bi bi-upload ms-1"></i>{{ $photo ? 'جایگزینی تصویر' : 'ثبت تصویر' }}<input class="d-none case-photo-input" type="file" accept="image/*" data-type="{{ $type }}" data-url="{{ route('maintenance-cases.photos.store', [$case, $type]) }}"></label>
                                        @else
                                            <span class="small text-muted">تصاویر در وضعیت فعلی فقط قابل مشاهده هستند.</span>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="case-card" aria-labelledby="operations-title">
                    <div class="case-card-head"><div><h2 id="operations-title">عملیات پرونده</h2><p>برای مشاهده جزئیات هر عملیات کارت را باز کنید؛ لمس کارت هیچ داده‌ای را حذف نمی‌کند.</p></div><span class="status-badge">{{ $faCount($activeOperations->count()) }} عملیات</span></div>
                    <div class="case-card-body">
                        @if($activeOperations->isEmpty())
                            <div class="empty-state"><i class="bi bi-tools"></i>هنوز عملیاتی برای این پرونده ثبت نشده است.</div>
                        @else
                            <div class="operation-list">
                                @foreach($activeOperations as $index => $operation)
                                    @php($operationStatus = $operationStatusLabels[$operation->status] ?? $operation->status)
                                    <details class="operation-card" {{ $loop->first ? 'open' : '' }}>
                                        <summary>
                                            <div class="operation-title"><span class="operation-number">{{ $faCount($index + 1) }}</span><div><strong>{{ $operation->operationCategory?->title ?? 'عملیات تعمیراتی' }}</strong><small>{{ $operation->operation_date ?? '—' }} · {{ $operation->description ?: 'بدون توضیحات' }}</small></div></div>
                                            <div class="operation-summary"><span class="status-badge {{ in_array($operation->status,['approved','completed'],true) ? 'success' : (in_array($operation->status,['need_revision','rejected'],true) ? 'warning' : '') }}">{{ $operationStatus }}</span><span class="status-badge">{{ $faCount($operation->operationMaterial->count()) }} مصالح</span><i class="bi bi-chevron-down"></i></div>
                                        </summary>
                                        <div class="operation-body">
                                            <p>{{ $operation->description ?: 'برای این عملیات توضیحی ثبت نشده است.' }}</p>
                                            <div class="operation-materials">@forelse($operation->operationMaterial as $material)<span class="material-chip">{{ $material->title }} <b>{{ $material->pivot->qty }} {{ $material->unit }}</b></span>@empty<span class="text-muted small">مصالحی ثبت نشده است.</span>@endforelse</div>
                                            @if($canReview && $operation->status === 'submitOperation')
                                                <div class="d-flex flex-wrap gap-2 mt-3"><button class="btn btn-success review-operation" data-url="{{ route('maintenance-cases.review', $case) }}" data-operation-id="{{ $operation->id }}" data-decision="approved"><i class="bi bi-check2"></i> تأیید عملیات</button><button class="btn btn-outline-danger review-operation" data-url="{{ route('maintenance-cases.review', $case) }}" data-operation-id="{{ $operation->id }}" data-decision="returned"><i class="bi bi-arrow-return-right"></i> بازگشت برای اصلاح</button></div>
                                            @endif
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>

                <section class="case-card" aria-labelledby="materials-title">
                    <div class="case-card-head"><div><h2 id="materials-title">خلاصه مصالح</h2><p>تجمیع مصالح عملیات فعال بر اساس material_id</p></div></div>
                    <div class="case-card-body">
                        @if($materialsSummary->isEmpty())<div class="empty-state"><i class="bi bi-box-seam"></i>مصالحی برای عملیات فعال ثبت نشده است.</div>
                        @else
                            <div class="table-responsive"><table class="material-table"><thead><tr><th>مصالح</th><th>واحد</th><th>مقدار کل</th><th>عملیات مرتبط</th></tr></thead><tbody>@foreach($materialsSummary as $material)<tr><td>{{ $material['title'] }}</td><td>{{ $material['unit'] ?: '—' }}</td><td class="qty">{{ $material['qty'] }}</td><td>{{ $faCount(count($material['operations'])) }} عملیات</td></tr>@endforeach</tbody></table></div>
                        @endif
                    </div>
                </section>
            </main>

            <aside class="d-grid gap-3">
                <section class="case-card">
                    <div class="case-card-head"><div><h2>اقدام بعدی</h2><p>فقط اقدامات مجاز برای این کاربر و وضعیت</p></div></div>
                    <div class="case-card-body d-grid gap-2">
                        @if($canEditOperations)
                            @if($activeOperations->isEmpty())<a class="btn btn-primary btn-lg" href="{{ route('maintenance-cases.operations.create', $case) }}"><i class="bi bi-plus-circle"></i> ثبت گروهی تعمیرات</a>
                            @else<a class="btn btn-primary btn-lg" href="{{ route('maintenance-cases.operations.edit', $case) }}"><i class="bi bi-pencil-square"></i> ویرایش تعمیرات</a>@endif
                        @elseif(in_array($case->status,['submitted','completed'],true))
                            <div class="workflow-return">ویرایش برای کاربر عادی در وضعیت «{{ $status }}» مجاز نیست.</div>
                        @endif
                        @if($canSubmitOperations)<button class="btn btn-outline-primary submit-operations" data-url="{{ route('maintenance-cases.operations.submit', $case) }}"><i class="bi bi-send"></i> ارسال برای تأیید</button>@endif
                        @if(!$canEditOperations && !$canSubmitOperations && !$canReview)<div class="empty-state py-3"><i class="bi bi-lock"></i>اقدام قابل اجرا برای دسترسی فعلی وجود ندارد.</div>@endif
                    </div>
                </section>
                <section class="case-card">
                    <div class="case-card-head"><div><h2>تاریخچه پرونده</h2><p>Audit و گردش‌کار append-only</p></div></div>
                    <div class="case-card-body"><div class="timeline">@forelse($timeline as $item)<div class="timeline-item"><span class="timeline-dot"></span><div class="timeline-content"><strong>{{ $item['title'] }}</strong><small>{{ $item['at']?->format('Y/m/d H:i') ?? '—' }} · {{ $item['actor'] ?: 'سیستم' }}</small>@if(is_string($item['detail']))<p>{{ $item['detail'] }}</p>@elseif(is_array($item['detail']) && !empty($item['detail']))<p>{{ json_encode($item['detail'], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</p>@endif</div></div>@empty<div class="empty-state py-3"><i class="bi bi-clock-history"></i>تاریخی برای این پرونده ثبت نشده است.</div>@endforelse</div></div>
                </section>
            </aside>
        </div>

        <div class="sticky-actions d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span class="small text-muted"><i class="bi bi-info-circle ms-1"></i>تصاویر قبل و بعد در سطح پرونده مدیریت می‌شوند و به عملیات متصل نیستند.</span>
            <a class="btn btn-outline-secondary" href="{{ url('/riser/'.$case->riser_id) }}"><i class="bi bi-arrow-right"></i> بازگشت به علمک</a>
        </div>
    </div>
</div>

<script>
(() => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const message = document.getElementById('case-message');
    const showMessage = (text, ok = false) => { message.textContent = text; message.className = 'alert case-message show mt-3 alert-' + (ok ? 'success' : 'danger'); };
    document.querySelectorAll('.case-photo-input').forEach(input => input.addEventListener('change', async () => {
        if (!input.files?.length) return;
        const form = new FormData(); form.append('photo', input.files[0]);
        input.disabled = true;
        try {
            const response = await fetch(input.dataset.url, { method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':token}, body:form });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'ثبت تصویر انجام نشد.');
            const card = input.closest('[data-photo-card]'); const frame = card.querySelector('.photo-frame');
            frame.innerHTML = '<a href="' + result.photo.url + '" target="_blank" rel="noopener"><img src="' + result.photo.url + '?v=' + Date.now() + '" alt="تصویر پرونده"></a>';
            showMessage('تصویر با موفقیت ثبت شد.', true);
        } catch (error) { showMessage(error.message || 'خطا در ثبت تصویر.'); }
        input.disabled = false; input.value = '';
    }));
    document.querySelectorAll('.review-operation').forEach(button => button.addEventListener('click', async () => {
        let comment = '';
        if (button.dataset.decision === 'returned') { comment = window.prompt('دلیل بازگشت برای اصلاح را وارد کنید:') || ''; if (!comment) return; }
        button.disabled = true;
        const response = await fetch(button.dataset.url, { method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':token}, body:JSON.stringify({operation_id:Number(button.dataset.operationId),decision:button.dataset.decision,comment}) });
        if (response.ok) window.location.reload(); else { showMessage('ثبت تصمیم انجام نشد؛ دسترسی و وضعیت پرونده را بررسی کنید.'); button.disabled = false; }
    }));
    document.querySelector('.submit-operations')?.addEventListener('click', async event => {
        const button = event.currentTarget; if (!window.confirm('همه عملیات فعال برای تأیید ارسال شوند؟')) return;
        button.disabled = true;
        try {
            const response = await fetch(button.dataset.url, { method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':token} });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) {
                const details = Object.values(result.errors || {}).flat().join(' ');
                throw new Error(result.message || details || 'ارسال پرونده انجام نشد.');
            }
            window.location.reload();
        } catch (error) {
            showMessage(error.message || 'ارسال پرونده انجام نشد.');
            button.disabled = false;
        }
    });
})();
</script>
@endsection
