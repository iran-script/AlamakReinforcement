@extends('layouts.app')

@section('title', 'ثبت تعمیرات گروهی')

@push('styles')
<style>
    .batch-page { --batch-ink:#17233f; --batch-muted:#667085; --batch-line:#e5e7eb; --batch-soft:#f7f9fc; padding-bottom:150px; }
    .batch-hero { background:linear-gradient(135deg,#162a55,#244b8f); color:#fff; border-radius:18px; padding:24px; box-shadow:0 12px 30px rgba(25,55,110,.16); }
    .batch-hero .eyebrow { color:#cbd8f4; font-size:12px; margin-bottom:5px; }
    .batch-hero h1 { font-size:24px; font-weight:800; margin:0 0 8px; }
    .batch-hero .case-meta { color:#e2e8f6; font-size:13px; }
    .batch-hero .case-code { display:inline-flex; direction:ltr; font-family:var(--font-mono); background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.22); padding:6px 10px; border-radius:8px; }
    .batch-workspace { display:grid; grid-template-columns:minmax(260px,34%) minmax(0,66%); gap:18px; align-items:start; }
    .batch-panel,.operation-card,.batch-preview { background:var(--surface); border:1px solid var(--batch-line); border-radius:16px; box-shadow:0 5px 18px rgba(16,24,40,.05); }
    .operation-nav { position:sticky; top:18px; max-height:calc(100vh - 36px); display:flex; flex-direction:column; overflow:hidden; }
    .panel-heading { padding:18px 20px; border-bottom:1px solid var(--batch-line); }
    .panel-heading h2 { color:var(--batch-ink); font-size:16px; font-weight:800; margin:0; }
    .panel-heading p { color:var(--batch-muted); font-size:12px; margin:5px 0 0; }
    .operation-search { padding:13px 16px; border-bottom:1px solid var(--batch-line); background:#fbfcff; }
    .operation-list { overflow:auto; padding:10px; }
    .operation-option { position:relative; display:block; cursor:pointer; margin:0 0 8px; }
    .operation-option[hidden] { display:none!important; }
    .operation-option .option-control { position:absolute; opacity:0; pointer-events:none; }
    .option-body { display:flex; align-items:center; gap:12px; min-height:68px; border:1px solid var(--batch-line); border-radius:12px; padding:11px 12px; background:#fff; cursor:pointer; transition:.18s ease; }
    .option-body:hover,.operation-option:focus-within .option-body { border-color:#9db7ef; box-shadow:0 4px 12px rgba(36,75,143,.08); }
    .operation-option.is-selected .option-body { border-color:#3264c2; background:#f1f5ff; box-shadow:0 0 0 3px rgba(50,100,194,.10); }
    .option-check { flex:0 0 23px; width:23px; height:23px; border:2px solid #bdc5d2; border-radius:7px; display:grid; place-items:center; color:#fff; }
    .operation-option.is-selected .option-check { background:#3264c2; border-color:#3264c2; }
    .option-check::after { content:'✓'; font-size:14px; opacity:0; }
    .operation-option.is-selected .option-check::after { opacity:1; }
    .option-title { color:var(--batch-ink); font-weight:800; font-size:14px; }
    .option-code { color:var(--batch-muted); direction:ltr; display:inline-block; font-size:11px; }
    .option-meta { color:var(--batch-muted); font-size:11px; }
    .option-error { display:none; color:#b42318; font-size:11px; }
    .operation-option.has-error .option-error { display:block; }
    .operation-option.has-error .option-body { border-color:#d92d20; }
    .selected-empty { border:1px dashed #cfd5df; border-radius:14px; color:var(--batch-muted); padding:28px 16px; text-align:center; background:var(--batch-soft); }
    .detail-panel { min-width:0; }
    .detail-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
    .detail-toolbar h2 { color:var(--batch-ink); font-size:19px; font-weight:800; margin:0; }
    .detail-toolbar p { color:var(--batch-muted); font-size:12px; margin:4px 0 0; }
    .mobile-operation-picker { display:none; }
    .operation-card { display:none; overflow:hidden; }
    .operation-card.is-active { display:block; }
    .operation-card.is-invalid { border-color:#d92d20; }
    .operation-header { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:17px 19px; background:#f8faff; border-bottom:1px solid var(--batch-line); }
    .operation-heading { display:flex; align-items:center; gap:11px; min-width:0; }
    .operation-number { width:34px; height:34px; border-radius:10px; display:grid; place-items:center; color:#244b8f; background:#e9efff; font-weight:800; flex:0 0 auto; }
    .operation-name { color:var(--batch-ink); font-weight:800; font-size:17px; }
    .operation-sub { color:var(--batch-muted); font-size:11px; margin-top:3px; }
    .operation-actions { display:flex; align-items:center; gap:8px; }
    .operation-body { padding:20px; background:#fcfdff; }
    .operation-body label { color:var(--batch-ink); font-size:13px; font-weight:700; }
    .operation-body .form-control,.operation-body .btn,.operation-search .form-control,.batch-dock .btn { min-height:44px; }
    .materials-box { border:1px solid var(--batch-line); border-radius:14px; background:#fff; padding:15px; }
    .materials-heading { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
    .materials-heading strong { color:var(--batch-ink); }
    .materials-heading small { display:block; color:var(--batch-muted); font-size:11px; margin-top:4px; }
    .materials-list { display:grid; gap:8px; }
    .material-row { display:grid; grid-template-columns:minmax(0,1fr) 140px 75px 44px; align-items:center; gap:9px; padding:10px 0; border-bottom:1px solid #eef0f4; }
    .material-row:last-child { border-bottom:0; }
    .material-title { color:var(--batch-ink); font-weight:700; font-size:13px; }
    .material-code { color:var(--batch-muted); direction:ltr; font-size:11px; }
    .material-unit { color:var(--batch-muted); font-size:12px; text-align:center; }
    .material-row .material-qty { min-height:48px; font-size:16px; text-align:center; }
    .material-row.is-invalid .material-qty { border-color:#d92d20; }
    .material-error { color:#b42318; font-size:11px; grid-column:1 / -1; }
    .material-empty { padding:17px 4px 5px; color:var(--batch-muted); font-size:12px; text-align:center; }
    .field-error { color:#b42318; font-size:12px; margin-top:10px; }
    .batch-preview { margin-top:18px; padding:15px 18px; }
    .batch-preview h3 { color:var(--batch-ink); font-size:14px; font-weight:800; margin:0 0 10px; }
    .preview-list { display:flex; flex-wrap:wrap; gap:8px; }
    .preview-chip { display:inline-flex; align-items:center; gap:6px; border:1px solid #dbe4f5; border-radius:999px; background:#f4f7ff; color:#244b8f; padding:7px 11px; font-size:12px; }
    .batch-dock { position:fixed; z-index:20; inset-inline:calc(var(--sidebar-w) + 28px) 28px; bottom:18px; }
    .batch-dock-inner { display:flex; align-items:center; justify-content:space-between; gap:16px; background:rgba(255,255,255,.97); backdrop-filter:blur(12px); border:1px solid #dbe2ee; border-radius:15px; padding:12px 15px; box-shadow:0 12px 35px rgba(16,24,40,.16); }
    .summary-stat { color:var(--batch-muted); font-size:12px; }
    .summary-stat strong { color:var(--batch-ink); font-size:18px; margin-inline-end:4px; }
    .material-modal-backdrop { position:fixed; z-index:40; inset:0; display:none; align-items:center; justify-content:center; padding:18px; background:rgba(15,23,42,.45); }
    .material-modal-backdrop.is-open { display:flex; }
    .material-modal { width:min(700px,100%); max-height:min(760px,90vh); display:flex; flex-direction:column; background:#fff; border-radius:18px; overflow:hidden; box-shadow:0 25px 70px rgba(15,23,42,.25); }
    .material-modal-head { padding:18px; border-bottom:1px solid var(--batch-line); }
    .material-modal-head h2 { color:var(--batch-ink); font-size:17px; font-weight:800; }
    .material-modal-list { overflow:auto; padding:10px 18px; }
    .material-choice { display:flex; align-items:center; gap:12px; min-height:60px; padding:10px 3px; border-bottom:1px solid #f0f1f4; cursor:pointer; }
    .material-choice input { width:22px; height:22px; accent-color:#3264c2; flex:0 0 auto; }
    .material-choice.is-disabled { opacity:.5; cursor:not-allowed; }
    .material-modal-foot { display:flex; justify-content:flex-end; gap:8px; padding:13px 18px; border-top:1px solid var(--batch-line); }
    .material-modal-foot .btn,.material-modal-head .form-control,.material-modal-head .form-select { min-height:44px; }
    .sheet-close { display:none; }
    @media (max-width:991.98px) {
        .batch-workspace { grid-template-columns:1fr; }
        .operation-nav { position:fixed; z-index:55; inset:0 auto 0 0; width:min(380px,92vw); max-height:none; border-radius:0 18px 18px 0; transform:translateX(-105%); transition:transform .2s ease; box-shadow:20px 0 50px rgba(15,23,42,.18); }
        [dir="rtl"] .operation-nav { inset:0 0 0 auto; transform:translateX(105%); border-radius:18px 0 0 18px; }
        .operation-nav.is-sheet-open,[dir="rtl"] .operation-nav.is-sheet-open { transform:translateX(0); }
        .sheet-close { display:inline-flex; }
        .mobile-operation-picker { display:flex; align-items:center; justify-content:space-between; gap:12px; width:100%; min-height:54px; margin-bottom:14px; padding:10px 14px; border:1px solid #cdd9f2; border-radius:14px; background:#f4f7ff; color:var(--batch-ink); }
        .batch-dock { inset-inline:16px; }
    }
    @media (max-width:575.98px) {
        .batch-page { padding-bottom:205px; }
        .batch-hero { padding:18px; }
        .batch-hero h1 { font-size:20px; }
        .operation-body { padding:14px; }
        .material-row { grid-template-columns:minmax(0,1fr) 96px 54px 44px; gap:5px; }
        .batch-dock-inner { align-items:stretch; flex-direction:column; gap:8px; }
        .batch-dock .btn { width:100%; }
        .batch-dock-inner > .d-flex { justify-content:space-between; gap:7px!important; }
        .summary-stat { font-size:10px; }
        .summary-stat strong { font-size:16px; }
        .material-modal-backdrop { align-items:flex-end; padding:0; }
        .material-modal { width:100%; max-height:92vh; border-radius:18px 18px 0 0; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid batch-page" dir="rtl">
    <div class="batch-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div><div class="eyebrow">پرونده تعمیراتی · ثبت مرحله‌ای</div><h1>ثبت تعمیرات گروهی</h1><div class="case-meta d-flex flex-wrap align-items-center gap-2"><span>پرونده #{{ $maintenanceCase->id }}</span><span>·</span><span class="case-code">{{ $maintenanceCase->riser?->r_giscode ?? $maintenanceCase->riser?->giscode ?? '—' }}</span><span>·</span><span>وضعیت: {{ $maintenanceCase->status }}</span></div></div>
            <a class="btn btn-light" href="{{ url('/riser/'.$maintenanceCase->riser_id) }}"><i class="bi bi-arrow-right"></i> جزئیات علمک</a>
        </div>
    </div>
    <div id="batch-message" class="alert d-none" role="alert" aria-live="polite"></div>
    <form id="batch-form" method="POST" action="{{ route('maintenance-cases.operations.store', $maintenanceCase) }}" enctype="multipart/form-data" novalidate>
        @csrf
        <input type="hidden" name="client_uuid" value="{{ \Illuminate\Support\Str::uuid() }}">
        <div class="batch-workspace">
            <aside class="batch-panel operation-nav" id="operation-nav" aria-label="فهرست عملیات">
                <div class="panel-heading d-flex justify-content-between align-items-start gap-2"><div><h2>انتخاب عملیات</h2><p>عملیات‌های موردنیاز را انتخاب کنید.</p></div><button type="button" class="btn btn-sm btn-outline-secondary sheet-close" id="close-operation-sheet">بستن</button></div>
                <div class="operation-search"><label class="visually-hidden" for="operation-search">جست‌وجوی عملیات</label><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input id="operation-search" class="form-control" type="search" placeholder="جست‌وجوی نام یا کد عملیات" autocomplete="off"></div></div>
                <div class="operation-list" id="operation-options">
                    @foreach($operationCategories as $category)
                        <label class="operation-option" data-search="{{ mb_strtolower(($category->title ?? '').' '.($category->code ?? '')) }}" data-category-id="{{ $category->id }}"><input class="option-control operation-toggle" type="checkbox" value="{{ $category->id }}" data-title="{{ $category->title }}" data-code="{{ $category->code }}"><span class="option-body"><span class="option-check" aria-hidden="true"></span><span class="flex-grow-1"><span class="option-title d-block">{{ $category->title }}</span><span class="option-code">{{ $category->code ?: 'بدون کد' }}</span><span class="option-meta d-block"><span class="option-material-count">۰</span> مصالح</span><span class="option-error">خطا دارد</span></span><i class="bi bi-chevron-left text-muted"></i></span></label>
                    @endforeach
                    <div id="operation-search-empty" class="selected-empty d-none">عملیاتی با این جست‌وجو پیدا نشد.</div>
                </div>
            </aside>
            <main class="detail-panel">
                <button type="button" class="mobile-operation-picker" id="open-operation-sheet"><span><i class="bi bi-list-check ms-1"></i> انتخاب یا تغییر عملیات</span><strong id="mobile-active-operation">هنوز عملیاتی انتخاب نشده</strong></button>
                <div class="detail-toolbar"><div><h2>جزئیات عملیات فعال</h2><p>هر عملیات را جداگانه تکمیل کنید؛ اطلاعات هنگام جابه‌جایی حفظ می‌شود.</p></div><span class="badge bg-primary" id="selected-count">۰ عملیات</span></div>
                <div id="operation-cards"></div><div id="selected-empty" class="selected-empty">هنوز عملیاتی انتخاب نشده است. از فهرست عملیات شروع کنید.</div>
                <section class="batch-preview" aria-live="polite"><h3><i class="bi bi-list-check ms-1"></i> خلاصه پیش از ثبت</h3><div id="preview-list" class="preview-list"><span class="text-muted small">پس از انتخاب عملیات، خلاصه اینجا نمایش داده می‌شود.</span></div></section>
            </main>
        </div>
    </form>
</div>
<div class="batch-dock"><div class="batch-dock-inner"><div class="d-flex gap-4"><div class="summary-stat"><strong id="dock-operation-count">۰</strong> عملیات</div><div class="summary-stat"><strong id="dock-material-count">۰</strong> نوع مصالح</div><div class="summary-stat"><strong id="dock-material-qty">۰</strong> مقدار کل</div><div class="summary-stat d-none" id="dock-error-stat"><strong id="dock-error-count">۰</strong> خطا</div></div><button form="batch-form" class="btn btn-primary px-4" id="submit-batch" type="submit"><i class="bi bi-check2-circle"></i> ثبت همه تعمیرات</button></div></div>
<div class="material-modal-backdrop" id="material-modal" role="dialog" aria-modal="true" aria-labelledby="material-modal-title"><div class="material-modal"><div class="material-modal-head"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 mb-0" id="material-modal-title">افزودن مصالح</h2><button type="button" class="btn-close" id="close-material-modal" aria-label="بستن"></button></div><div class="row g-2"><div class="col"><input id="material-search" class="form-control" type="search" placeholder="جست‌وجوی نام یا کد مصالح" autocomplete="off"></div><div class="col-auto"><select id="material-category" class="form-select" aria-label="فیلتر دسته مصالح"><option value="">همه دسته‌ها</option></select></div></div></div><div id="material-modal-list" class="material-modal-list"></div><div class="material-modal-foot"><button type="button" class="btn btn-outline-secondary" id="cancel-material-modal">انصراف</button><button type="button" class="btn btn-primary" id="apply-material-modal">افزودن انتخاب‌ها</button></div></div></div>
@endsection

@push('scripts')
<script>
(() => {
    'use strict';
    const form = document.getElementById('batch-form');
    const catalog = {{ \Illuminate\Support\Js::from($materialsPayload) }};
    const indexUrl = {{ \Illuminate\Support\Js::from(route('maintenance-cases.index')) }};
    const cards = new Map(); const modal = document.getElementById('material-modal'); const operationNav = document.getElementById('operation-nav');
    let activeId = null; let modalCard = null; let modalSelected = new Set();
    const fa = value => Number(value || 0).toLocaleString('fa-IR', { maximumFractionDigits: 4 });
    const normalize = value => String(value || '').toLocaleLowerCase('fa-IR').replace(/[يىی]/g, 'ی').replace(/[كک]/g, 'ک').trim();
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    const materialById = id => catalog.find(item => String(item.id) === String(id));
    const selectedCards = () => [...cards.values()];
    const setMessage = (text, type = 'danger') => { const message = document.getElementById('batch-message'); message.textContent = text; message.className = `alert alert-${type}`; };
    const closeOperationSheet = () => { operationNav.classList.remove('is-sheet-open'); document.body.classList.remove('modal-open'); };
    const updateSummary = () => { const selected = selectedCards(); const kinds = new Set(); let total = 0; selected.forEach(card => { const rows = card.querySelectorAll('.material-row'); document.querySelector(`.operation-option[data-category-id="${card.dataset.category}"] .option-material-count`)?.replaceChildren(fa(rows.length)); rows.forEach(row => { const material = materialById(row.dataset.materialId); const qty = Number(row.querySelector('.material-qty')?.value || 0); if (material) kinds.add(material.id); if (qty > 0) total += qty; }); }); document.getElementById('selected-count').textContent = `${fa(selected.length)} عملیات`; document.getElementById('dock-operation-count').textContent = fa(selected.length); document.getElementById('dock-material-count').textContent = fa(kinds.size); document.getElementById('dock-material-qty').textContent = fa(total); document.getElementById('selected-empty').classList.toggle('d-none', selected.length > 0); document.getElementById('mobile-active-operation').textContent = activeId && cards.get(activeId) ? cards.get(activeId).dataset.title : 'هنوز عملیاتی انتخاب نشده'; document.getElementById('preview-list').innerHTML = selected.length ? selected.map(card => `<span class="preview-chip"><i class="bi bi-check2"></i>${escapeHtml(card.dataset.title)} <small>(${fa(card.querySelectorAll('.material-row').length)} مصالح)</small></span>`).join('') : '<span class="text-muted small">پس از انتخاب عملیات، خلاصه اینجا نمایش داده می‌شود.</span>'; };
    const refreshMaterialEmpty = card => { card.querySelector('.material-empty').classList.toggle('d-none', card.querySelectorAll('.material-row').length > 0); updateSummary(); };
    const activateCard = id => { activeId = String(id); cards.forEach(card => card.classList.toggle('is-active', card.dataset.category === activeId)); updateSummary(); closeOperationSheet(); };
    const addMaterialRow = (card, materialId) => { if (card.querySelector(`.material-row[data-material-id="${materialId}"]`)) return; const material = materialById(materialId); if (!material) return; const row = document.createElement('div'); row.className = 'material-row'; row.dataset.materialId = material.id; row.innerHTML = `<div><div class="material-title">${escapeHtml(material.title)}</div><div class="material-code">${escapeHtml(material.code || 'بدون کد')}</div></div><input type="hidden" name="operations[${card.dataset.category}][materials][${material.id}][material_id]" value="${material.id}"><input class="form-control material-qty" type="number" min="0.0001" step="any" placeholder="مقدار" name="operations[${card.dataset.category}][materials][${material.id}][qty]" required><div class="material-unit">${escapeHtml(material.unit || '—')}</div><button type="button" class="btn btn-sm btn-outline-danger remove-material" aria-label="حذف ${escapeHtml(material.title)}"><i class="bi bi-trash3"></i></button><div class="material-error d-none"></div>`; row.querySelector('.material-qty').addEventListener('input', () => { row.classList.remove('is-invalid'); row.querySelector('.material-error').classList.add('d-none'); updateSummary(); }); row.querySelector('.remove-material').addEventListener('click', () => { row.remove(); refreshMaterialEmpty(card); }); card.querySelector('.materials-list').append(row); refreshMaterialEmpty(card); };
    const renderMaterialModal = () => { if (!modalCard) return; const term = normalize(document.getElementById('material-search').value); const category = document.getElementById('material-category').value; const already = new Set([...modalCard.querySelectorAll('.material-row')].map(row => String(row.dataset.materialId))); const visible = catalog.filter(item => (!category || String(item.category_id) === category) && (!term || normalize(`${item.title} ${item.code}`).includes(term))); document.getElementById('material-modal-list').innerHTML = visible.length ? visible.map(item => { const id = String(item.id); const disabled = already.has(id); return `<label class="material-choice ${disabled ? 'is-disabled' : ''}"><input type="checkbox" value="${id}" ${modalSelected.has(id) || disabled ? 'checked' : ''} ${disabled ? 'disabled' : ''}><span><strong>${escapeHtml(item.title)}</strong><small class="d-block text-muted"><span dir="ltr">${escapeHtml(item.code || 'بدون کد')}</span> · ${escapeHtml(item.unit || '—')} · ${escapeHtml(item.category_title || 'بدون دسته')}</small></span></label>`; }).join('') : '<div class="selected-empty">مصالحی با این جست‌وجو پیدا نشد.</div>'; document.querySelectorAll('#material-modal-list input:not(:disabled)').forEach(input => input.addEventListener('change', event => event.target.checked ? modalSelected.add(event.target.value) : modalSelected.delete(event.target.value))); };
    const openMaterialModal = card => { modalCard = card; modalSelected = new Set(); document.getElementById('material-search').value = ''; renderMaterialModal(); modal.classList.add('is-open'); document.getElementById('material-search').focus(); };
    const closeMaterialModal = () => { modal.classList.remove('is-open'); modalCard = null; };
    const renumberCards = () => selectedCards().forEach((card, index) => card.querySelector('.operation-number').textContent = fa(index + 1));
    const createCard = toggle => { const category = {id:toggle.value, title:toggle.dataset.title, code:toggle.dataset.code}; const card = document.createElement('article'); card.className = 'operation-card'; card.dataset.category = category.id; card.dataset.title = category.title; card.innerHTML = `<div class="operation-header"><div class="operation-heading"><span class="operation-number">${cards.size + 1}</span><div><div class="operation-name">${escapeHtml(category.title)}</div><div class="operation-sub">کد ${escapeHtml(category.code || '—')} · <span class="material-count">۰</span> مصالح</div></div></div><div class="operation-actions"><button type="button" class="btn btn-sm btn-outline-danger remove-operation">حذف عملیات</button><i class="bi bi-chevron-down text-muted"></i></div></div><div class="operation-body"><input type="hidden" name="operations[${category.id}][operation_category_id]" value="${category.id}"><div class="row g-3"><div class="col-md-4"><label class="form-label" for="operation-date-${category.id}">تاریخ عملیات</label><input id="operation-date-${category.id}" class="form-control" type="date" name="operations[${category.id}][operation_date]" value="${new Date().toISOString().slice(0,10)}" required></div><div class="col-md-8"><label class="form-label" for="operation-description-${category.id}">شرح مستقل عملیات</label><textarea id="operation-description-${category.id}" class="form-control" name="operations[${category.id}][description]" rows="3" maxlength="10000" placeholder="شرح کوتاه و قابل پیگیری"></textarea></div></div><div class="materials-box mt-4"><div class="materials-heading"><div><strong>مصالح مصرفی</strong><small>برای هر مصالح مقدار مثبت و واحد واقعی را وارد کنید.</small></div><button type="button" class="btn btn-outline-primary add-material"><i class="bi bi-plus-lg"></i> افزودن مصالح</button></div><div class="materials-list"></div><div class="material-empty">هنوز مصالحی برای این عملیات انتخاب نشده است.</div></div><div class="mt-3"><label class="form-label" for="operation-photos-${category.id}">تصاویر مرتبط با عملیات (اختیاری)</label><input id="operation-photos-${category.id}" class="form-control" type="file" name="operations[${category.id}][photos_after][]" accept="image/*" multiple></div><div class="field-error operation-error d-none" role="alert"></div></div>`; card.querySelector('.operation-header').addEventListener('click', event => { if (!event.target.closest('button')) activateCard(category.id); }); card.querySelector('.remove-operation').addEventListener('click', () => { const hasData = card.querySelectorAll('.material-row').length || card.querySelector('textarea').value.trim(); if (hasData && !window.confirm('اطلاعات این عملیات حذف شود؟')) return; toggle.checked = false; toggle.closest('.operation-option').classList.remove('is-selected'); card.remove(); cards.delete(String(category.id)); if (activeId === String(category.id)) activeId = selectedCards()[0]?.dataset.category || null; renumberCards(); updateSummary(); if (activeId) activateCard(activeId); }); card.querySelector('.add-material').addEventListener('click', () => openMaterialModal(card)); card.querySelectorAll('input, textarea').forEach(input => input.addEventListener('input', updateSummary)); document.getElementById('operation-cards').append(card); cards.set(String(category.id), card); activateCard(category.id); };
    const syncOptionOrder = () => { const list = document.getElementById('operation-options'); [...list.querySelectorAll('.operation-option')].sort((a,b) => Number(b.querySelector('.operation-toggle').checked) - Number(a.querySelector('.operation-toggle').checked)).forEach(option => list.append(option)); };
    document.querySelectorAll('.operation-toggle').forEach(toggle => toggle.addEventListener('change', () => { const id = String(toggle.value); const option = toggle.closest('.operation-option'); option.classList.toggle('is-selected', toggle.checked); option.classList.remove('has-error'); if (toggle.checked) createCard(toggle); else { cards.get(id)?.remove(); cards.delete(id); if (activeId === id) activeId = selectedCards()[0]?.dataset.category || null; if (activeId) activateCard(activeId); else updateSummary(); } syncOptionOrder(); }));
    document.getElementById('operation-search').addEventListener('input', event => { const term = normalize(event.target.value); let visible = 0; document.querySelectorAll('.operation-option').forEach(option => { const show = !term || normalize(option.dataset.search).includes(term); option.hidden = !show; if (show) visible++; }); document.getElementById('operation-search-empty').classList.toggle('d-none', visible > 0); });
    document.getElementById('open-operation-sheet').addEventListener('click', () => { operationNav.classList.add('is-sheet-open'); document.body.classList.add('modal-open'); }); document.getElementById('close-operation-sheet').addEventListener('click', closeOperationSheet);
    document.getElementById('close-material-modal').addEventListener('click', closeMaterialModal); document.getElementById('cancel-material-modal').addEventListener('click', closeMaterialModal); modal.addEventListener('click', event => { if (event.target === modal) closeMaterialModal(); }); document.getElementById('material-search').addEventListener('input', renderMaterialModal); document.getElementById('material-category').addEventListener('change', renderMaterialModal); document.getElementById('apply-material-modal').addEventListener('click', () => { if (modalCard) modalSelected.forEach(id => addMaterialRow(modalCard, id)); closeMaterialModal(); });
    [...new Map(catalog.filter(item => item.category_id).map(item => [String(item.category_id), item.category_title])).entries()].forEach(([id, title]) => { const option = document.createElement('option'); option.value = id; option.textContent = title || 'بدون عنوان'; document.getElementById('material-category').append(option); });
    form.addEventListener('submit', async event => { event.preventDefault(); const selected = selectedCards(); const button = document.getElementById('submit-batch'); const errorStat = document.getElementById('dock-error-stat'); if (!selected.length) { setMessage('حداقل یک عملیات را انتخاب کنید.'); return; } button.disabled = true; button.innerHTML = '<span class="spinner-border spinner-border-sm ms-1"></span> در حال ثبت…'; document.querySelectorAll('.operation-error,.material-error').forEach(error => { error.textContent = ''; error.classList.add('d-none'); }); document.querySelectorAll('.operation-card,.operation-option').forEach(element => element.classList.remove('is-invalid','has-error')); errorStat.classList.add('d-none'); try { const response = await fetch(form.action, { method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content}, body:new FormData(form) }); const result = await response.json(); if (!response.ok) { let errors = 0; Object.entries(result.errors || {}).forEach(([key, values]) => { const match = key.match(/^operations\.([^\.]+)/); if (!match) return; const card = cards.get(String(match[1])); if (!card) return; errors++; card.classList.add('is-invalid'); document.querySelector(`.operation-option[data-category-id="${match[1]}"]`)?.classList.add('has-error'); const operationError = card.querySelector('.operation-error'); operationError.textContent = (Array.isArray(values) ? values : [values]).join(' '); operationError.classList.remove('d-none'); const materialMatch = key.match(/materials\.([^\.]+)\.qty$/); if (materialMatch) { const row = card.querySelector(`.material-row[data-material-id="${materialMatch[1]}"]`); if (row) { row.classList.add('is-invalid'); row.querySelector('.material-error').textContent = (Array.isArray(values) ? values : [values]).join(' '); row.querySelector('.material-error').classList.remove('d-none'); } } }); if (errors) { errorStat.classList.remove('d-none'); document.getElementById('dock-error-count').textContent = fa(errors); const first = Object.keys(result.errors || {})[0]; activateCard(first ? String(first).split('.')[1] : activeId); } throw new Error(result.message || 'اطلاعات ثبت نشد؛ خطاهای هر عملیات را بررسی کنید.'); } setMessage(result.message || `ثبت ${fa(result.operations?.length || selected.length)} عملیات با موفقیت انجام شد.`, 'success'); setTimeout(() => { window.location.href = result.redirect_url || indexUrl; }, 800); } catch (error) { setMessage(error.message || 'خطای شبکه؛ دوباره تلاش کنید.'); button.disabled = false; button.innerHTML = '<i class="bi bi-check2-circle"></i> ثبت همه تعمیرات'; } });
    updateSummary();
})();
</script>
@endpush
