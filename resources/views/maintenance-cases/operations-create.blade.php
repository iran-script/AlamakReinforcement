@extends('layouts.app')

@section('title', 'ثبت تعمیرات گروهی')
@php($editing = $editing ?? false)
@php($operationsPayload = $operationsPayload ?? [])

@push('styles')
<style>
    .batch-page { --batch-ink:#17233f; --batch-muted:#667085; --batch-line:#e5e7eb; --batch-soft:#f7f9fc; padding-bottom:150px; }
    .batch-hero { background:linear-gradient(135deg,#162a55,#244b8f); color:#fff; border-radius:18px; padding:24px; box-shadow:0 12px 30px rgba(25,55,110,.16); }
    .batch-hero .eyebrow { color:#cbd8f4; font-size:12px; margin-bottom:5px; }
    .batch-hero h1 { font-size:24px; font-weight:800; margin:0 0 8px; }
    .batch-hero .case-meta { color:#e2e8f6; font-size:13px; }
    .batch-hero .case-code { display:inline-flex; direction:ltr; font-family:var(--font-mono); background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.22); padding:6px 10px; border-radius:8px; }
    .batch-workspace { display:grid; grid-template-columns:minmax(380px,42%) minmax(0,58%); gap:18px; align-items:start; }
    .batch-panel,.operation-card,.batch-preview { background:var(--surface); border:1px solid var(--batch-line); border-radius:16px; box-shadow:0 5px 18px rgba(16,24,40,.05); }
    .operation-nav { position:sticky; top:18px; display:flex; flex-direction:column; overflow:visible; }
    .panel-heading { padding:18px 20px; border-bottom:1px solid var(--batch-line); }
    .panel-heading h2 { color:var(--batch-ink); font-size:16px; font-weight:800; margin:0; }
    .panel-heading p { color:var(--batch-muted); font-size:12px; margin:5px 0 0; }
    .operation-search { padding:13px 16px; border-bottom:1px solid var(--batch-line); background:#fbfcff; }
    .operation-list { overflow:visible; padding:10px; }
    #existing-operation-list { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; padding:14px 16px 10px; }
    #existing-operation-list .operation-option { margin:0; min-width:0; }
    #existing-operation-list .option-body { min-height:72px; height:100%; padding:12px; align-items:flex-start; }
    #existing-operation-list .option-title { line-height:1.5; overflow-wrap:anywhere; }
    #existing-operation-list .option-meta { margin-top:4px; }
    .operation-select,.undo-operation { min-height:44px; padding:8px 6px!important; white-space:nowrap; }
    .operation-option { position:relative; display:block; cursor:pointer; margin:0 0 8px; }
    .operation-option[hidden] { display:none!important; }
    .operation-option { width:100%; border:0; padding:0; background:transparent; text-align:inherit; }
    .option-body { display:flex; align-items:center; gap:12px; min-height:68px; border:1px solid var(--batch-line); border-radius:12px; padding:11px 12px; background:#fff; cursor:pointer; transition:.18s ease; }
    .option-body:hover,.operation-option:focus-within .option-body { border-color:#9db7ef; box-shadow:0 4px 12px rgba(36,75,143,.08); }
    .option-check { border:0; border-radius:50%; width:30px; height:30px; flex-basis:30px; color:#244b8f; background:#e9efff; font-weight:800; }
    .operation-option.is-selected .option-body { border-color:#3264c2; background:#f1f5ff; box-shadow:0 0 0 3px rgba(50,100,194,.10); }
    .option-check { flex:0 0 23px; width:23px; height:23px; border:2px solid #bdc5d2; border-radius:7px; display:grid; place-items:center; color:#fff; }
    .operation-option.is-selected .option-check { background:#3264c2; border-color:#3264c2; }
    .option-check::after { content:'✓'; font-size:14px; opacity:0; }
    .operation-option.is-selected .option-check::after { opacity:1; }
    .option-title { color:var(--batch-ink); font-weight:800; font-size:14px; }
    .option-code { color:var(--batch-muted); direction:ltr; display:inline-block; font-size:11px; }
    .option-meta { color:var(--batch-muted); font-size:11px; }
    .operation-option.is-active .option-body { border-color:#244b8f; box-shadow:0 0 0 3px rgba(36,75,143,.14); }
    .operation-option.is-pending-delete { opacity:.62; }
    .operation-option.is-pending-delete .option-body { border-color:#d92d20; background:#fff7f6; }
    .operation-option .change-status { display:inline-block; margin-inline-start:5px; font-size:10px; }
    .selected-empty { border:1px dashed #cfd5df; border-radius:14px; color:var(--batch-muted); padding:28px 16px; text-align:center; background:var(--batch-soft); }
    .detail-panel { min-width:0; }
    .detail-toolbar { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:12px; }
    .detail-toolbar h2 { color:var(--batch-ink); font-size:19px; font-weight:800; margin:0; }
    .detail-toolbar p { color:var(--batch-muted); font-size:12px; margin:4px 0 0; }
    .mobile-operation-picker { display:none; }
    .detail-back-button { display:none; min-height:44px; }
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
        .operation-nav { position:fixed; z-index:55; inset:0 auto 0 0; width:min(760px,92vw); max-height:100vh; overflow-y:auto; border-radius:0 18px 18px 0; transform:translateX(-105%); transition:transform .2s ease; box-shadow:20px 0 50px rgba(15,23,42,.18); }
        [dir="rtl"] .operation-nav { inset:0 0 0 auto; transform:translateX(105%); border-radius:18px 0 0 18px; }
        .operation-nav.is-sheet-open,[dir="rtl"] .operation-nav.is-sheet-open { transform:translateX(0); }
        .sheet-close { display:inline-flex; }
        .mobile-operation-picker { display:flex; align-items:center; justify-content:space-between; gap:12px; width:100%; min-height:54px; margin-bottom:14px; padding:10px 14px; border:1px solid #cdd9f2; border-radius:14px; background:#f4f7ff; color:var(--batch-ink); }
        .detail-back-button { display:inline-flex; }
        .batch-dock { inset-inline:16px; }
    }
    @media (min-width:768px) and (max-width:991.98px) {
        #existing-operation-list { grid-template-columns:repeat(2,minmax(0,1fr)); }
    }
    @media (max-width:767.98px) {
        #existing-operation-list { grid-template-columns:1fr; }
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
            <div><div class="eyebrow">پرونده تعمیراتی · {{ $editing ? 'ویرایش مرحله‌ای' : 'ثبت مرحله‌ای' }}</div><h1>{{ $editing ? 'ویرایش تعمیرات گروهی' : 'ثبت تعمیرات گروهی' }}</h1><div class="case-meta d-flex flex-wrap align-items-center gap-2"><span>پرونده #{{ $maintenanceCase->id }}</span><span>·</span><span class="case-code">{{ $maintenanceCase->riser?->r_giscode ?? $maintenanceCase->riser?->giscode ?? '—' }}</span><span>·</span><span>وضعیت: {{ $maintenanceCase->status }}</span></div></div>
            <a class="btn btn-light" href="{{ url('/riser/'.$maintenanceCase->riser_id) }}"><i class="bi bi-arrow-right"></i> جزئیات علمک</a>
        </div>
    </div>
    <div id="batch-message" class="alert d-none" role="alert" aria-live="polite"></div>
    <form id="batch-form" method="POST" action="{{ $editing ? route('maintenance-cases.operations.update-batch', $maintenanceCase) : route('maintenance-cases.operations.store', $maintenanceCase) }}" novalidate>
        @csrf
        @if($editing)<input type="hidden" name="_method" value="PUT">@endif
        <input type="hidden" name="client_uuid" value="{{ \Illuminate\Support\Str::uuid() }}">
        <div class="batch-workspace">
            <aside class="batch-panel operation-nav" id="operation-nav" aria-label="فهرست عملیات">
                <div class="panel-heading d-flex justify-content-between align-items-start gap-2"><div><h2>{{ $editing ? "عملیات پرونده" : "انتخاب عملیات" }}</h2><p>{{ $editing ? "برای ویرایش، یک عملیات را فعال کنید." : "عملیات‌های موردنیاز را به فرم اضافه کنید." }}</p></div><button type="button" class="btn btn-sm btn-outline-secondary sheet-close" id="close-operation-sheet">بستن</button></div>
                <div class="operation-search"><label class="visually-hidden" for="operation-search">جست‌وجوی عملیات</label><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input id="operation-search" class="form-control" type="search" placeholder="جست‌وجوی نام یا کد عملیات" autocomplete="off"></div></div>
                <div id="existing-operation-list" class="operation-list d-none" aria-label="عملیات ثبت‌شده"></div>
                <div class="d-flex justify-content-between align-items-center px-3 pt-3"><strong class="small text-muted">{{ $editing ? "افزودن عملیات جدید" : "فهرست عملیات" }}</strong><button type="button" class="btn btn-sm btn-outline-primary" id="add-operation-button">افزودن عملیات</button><span id="unsaved-change-count" class="small text-muted"></span></div>
                <div class="operation-list" id="operation-options">
                    @foreach($operationCategories as $category)
                        <button type="button" class="operation-option operation-catalog-option" data-search="{{ mb_strtolower(($category->title ?? "")." ".($category->code ?? "")) }}" data-category-id="{{ $category->id }}" data-title="{{ $category->title }}" data-code="{{ $category->code }}"><span class="option-body"><span class="option-check" aria-hidden="true">+</span><span class="flex-grow-1"><span class="option-title d-block">{{ $category->title }}</span><span class="option-code">{{ $category->code ?: "بدون کد" }}</span><span class="option-meta d-block"><span class="option-material-count">افزودن</span></span><span class="option-error">خطا دارد</span></span><i class="bi bi-plus-lg text-muted"></i></span></button>
                    @endforeach
                    <div id="operation-search-empty" class="selected-empty d-none">عملیاتی با این جست‌وجو پیدا نشد.</div>
                </div>
            </aside>
            <main class="detail-panel">
                <button type="button" class="mobile-operation-picker" id="open-operation-sheet"><span><i class="bi bi-list-check ms-1"></i> انتخاب یا تغییر عملیات</span><strong id="mobile-active-operation">هنوز عملیاتی انتخاب نشده</strong></button>
                <button type="button" class="btn btn-outline-secondary detail-back-button mb-2" id="back-to-operation-list"><i class="bi bi-arrow-right ms-1"></i> بازگشت به فهرست عملیات</button>
                <div class="detail-toolbar"><div><h2>جزئیات عملیات فعال</h2><p>هر عملیات را جداگانه تکمیل کنید؛ اطلاعات هنگام جابه‌جایی حفظ می‌شود.</p></div><span class="badge bg-primary" id="selected-count">۰ عملیات</span></div>
                <div id="operation-cards"></div><div id="selected-empty" class="selected-empty">هنوز عملیاتی انتخاب نشده است. از فهرست عملیات شروع کنید.</div>
                <section class="batch-preview" aria-live="polite"><h3><i class="bi bi-list-check ms-1"></i> خلاصه پیش از ثبت</h3><div id="preview-list" class="preview-list"><span class="text-muted small">پس از انتخاب عملیات، خلاصه اینجا نمایش داده می‌شود.</span></div></section>
            </main>
        </div>
    </form>
</div>
<div class="batch-dock"><div class="batch-dock-inner"><div class="d-flex gap-4"><div class="summary-stat"><strong id="dock-operation-count">۰</strong> عملیات</div><div class="summary-stat"><strong id="dock-material-count">۰</strong> نوع مصالح</div><div class="summary-stat"><strong id="dock-material-qty">۰</strong> مقدار کل</div><div class="summary-stat d-none" id="dock-error-stat"><strong id="dock-error-count">۰</strong> خطا</div></div><button form="batch-form" class="btn btn-primary px-4" id="submit-batch" type="submit"><i class="bi bi-check2-circle"></i> {{ $editing ? 'ذخیره همه تغییرات' : 'ثبت همه تعمیرات' }}</button></div></div>
<div class="material-modal-backdrop" id="material-modal" role="dialog" aria-modal="true" aria-labelledby="material-modal-title"><div class="material-modal"><div class="material-modal-head"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h6 mb-0" id="material-modal-title">افزودن مصالح</h2><button type="button" class="btn-close" id="close-material-modal" aria-label="بستن"></button></div><div class="row g-2"><div class="col"><input id="material-search" class="form-control" type="search" placeholder="جست‌وجوی نام یا کد مصالح" autocomplete="off"></div><div class="col-auto"><select id="material-category" class="form-select" aria-label="فیلتر دسته مصالح"><option value="">همه دسته‌ها</option></select></div></div></div><div id="material-modal-list" class="material-modal-list"></div><div class="material-modal-foot"><button type="button" class="btn btn-outline-secondary" id="cancel-material-modal">انصراف</button><button type="button" class="btn btn-primary" id="apply-material-modal">افزودن انتخاب‌ها</button></div></div></div>
@endsection

@push('scripts')
<script>
(() => {
    'use strict';
    const form = document.getElementById('batch-form');
    const catalog = {{ \Illuminate\Support\Js::from($materialsPayload) }};
    const indexUrl = {{ \Illuminate\Support\Js::from(route('maintenance-cases.index')) }};
    const editing = {{ \Illuminate\Support\Js::from($editing) }};
    const initialOperations = {{ \Illuminate\Support\Js::from($operationsPayload) }};
    const operationNav = document.getElementById('operation-nav');
    const existingList = document.getElementById('existing-operation-list');
    const catalogList = document.getElementById('operation-options');
    const detailCards = document.getElementById('operation-cards');
    const emptyDetail = document.getElementById('selected-empty');
    const modal = document.getElementById('material-modal');
    const state = new Map();
    let activeOperationKey = null;
    let modalOperationKey = null;
    let nextClientKey = 0;
    const fa = value => Number(value || 0).toLocaleString('fa-IR', { maximumFractionDigits: 4 });
    const normalize = value => String(value || '').toLocaleLowerCase('fa-IR').replace(/[يىی]/g, 'ی').replace(/[كک]/g, 'ک').trim();
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
    const escapeAttr = value => escapeHtml(value);
    const normalizeMaterialId = value => {
        const text = String(value ?? '').trim();
        return /^\d+$/.test(text) ? String(Number(text)) : text;
    };
    const materialById = id => {
        const normalized = normalizeMaterialId(id);
        return catalog.find(item => normalizeMaterialId(item.id) === normalized);
    };
    const materialEntries = operation => {
        const entries = [];
        const seen = new Map();
        const duplicates = new Map();
        Object.entries(operation.materials || {}).forEach(([rawId, qty]) => {
            const materialId = normalizeMaterialId(rawId);
            if (seen.has(materialId)) {
                duplicates.set(materialId, [...(duplicates.get(materialId) || []), { rawId, qty }]);
                return;
            }
            seen.set(materialId, qty);
            entries.push({ materialId, qty });
        });
        return { entries, duplicates };
    };
    const materialDuplicateMessage = materialId => {
        const material = materialById(materialId);
        return 'مصالح «' + (material?.title || materialId) + '» در یک عملیات بیش از یک بار انتخاب شده است؛ یکی از ردیف‌ها را حذف کنید.';
    };
    const categoryById = id => [...catalogList.querySelectorAll('.operation-catalog-option')].find(item => String(item.dataset.categoryId) === String(id));
    const visibleOperations = () => [...state.values()].filter(operation => operation.changeStatus !== 'pending_delete');
    const dirty = () => [...state.values()].some(operation => operation.changeStatus !== 'unchanged');
    const markModified = operation => { if (operation.changeStatus === 'unchanged') operation.changeStatus = 'modified'; };
    const newClientKey = () => 'new-' + Date.now().toString(36) + '-' + (nextClientKey++);
    const activeOperation = () => activeOperationKey ? state.get(activeOperationKey) : null;
    const setMessage = (text, type = 'danger') => { const message = document.getElementById('batch-message'); message.textContent = text; message.className = 'alert alert-' + type; message.classList.remove('d-none'); };
    const closeOperationSheet = () => { operationNav.classList.remove('is-sheet-open'); document.body.classList.remove('modal-open'); };
    const openOperationSheet = () => { operationNav.classList.add('is-sheet-open'); document.body.classList.add('modal-open'); };
    const setActiveOperation = key => { const operation = state.get(String(key)); if (!operation || operation.changeStatus === 'pending_delete') return; activeOperationKey = String(key); renderAll(); closeOperationSheet(); };
    const operationStatusLabel = operation => operation.changeStatus === 'new' ? '<span class="change-status text-primary">جدید</span>' : operation.changeStatus === 'modified' ? '<span class="change-status text-warning">تغییر کرده</span>' : operation.changeStatus === 'pending_delete' ? '<span class="change-status text-danger">در انتظار حذف</span>' : '<span class="change-status text-muted">بدون تغییر</span>';

    const renderOperationList = () => {
        const operations = [...state.values()];
        existingList.classList.toggle('d-none', operations.length === 0);
        existingList.innerHTML = operations.length ? operations.map(operation => {
            const category = categoryById(operation.operation_category_id);
            const title = category?.dataset.title || operation.category_title || 'عملیات';
            const code = category?.dataset.code || operation.category_code || '—';
            const active = activeOperationKey === operation.client_key ? ' is-active' : '';
            const pending = operation.changeStatus === 'pending_delete' ? ' is-pending-delete' : '';
            const action = operation.changeStatus === 'pending_delete' ? '<button type="button" class="btn btn-sm btn-outline-success undo-operation" data-operation-key="' + escapeAttr(operation.client_key) + '">لغو حذف</button>' : '<button type="button" class="btn btn-sm btn-link p-0 operation-select" data-operation-key="' + escapeAttr(operation.client_key) + '">ویرایش</button>';
            return '<div class="operation-option' + active + pending + '" data-operation-key="' + escapeAttr(operation.client_key) + '" data-search="' + escapeAttr(normalize(title + ' ' + code)) + '"><div class="option-body"><span class="option-check">' + (operation.operation_id ? '✓' : '+') + '</span><span class="flex-grow-1"><span class="option-title d-block">' + escapeHtml(title) + operationStatusLabel(operation) + '</span><span class="option-code">' + escapeHtml(code) + '</span><span class="option-meta d-block">' + fa(Object.keys(operation.materials).length) + ' مصالح</span></span>' + action + '</div></div>';
        }).join('') : '';
    };

    const renderCatalog = () => {
        const term = normalize(document.getElementById('operation-search').value);
        let visible = 0;
        catalogList.querySelectorAll('.operation-catalog-option').forEach(option => {
            const categoryTerm = normalize(option.dataset.search || '');
            const already = [...state.values()].some(operation => operation.changeStatus !== 'pending_delete' && String(operation.operation_category_id) === String(option.dataset.categoryId));
            const matches = !term || categoryTerm.includes(term);
            option.hidden = !matches;
            option.disabled = already;
            option.classList.toggle('is-disabled', already);
            option.querySelector('.option-meta').textContent = already ? 'در فرم موجود است' : 'افزودن به فرم';
            if (matches) visible++;
        });
        document.getElementById('operation-search-empty').classList.toggle('d-none', visible > 0);
    };

    const renderMaterialRows = (operation, card) => {
        const list = card.querySelector('.materials-list');
        const empty = card.querySelector('.material-empty');
        list.innerHTML = Object.entries(operation.materials).map(([materialId, qty]) => {
            const normalizedMaterialId = normalizeMaterialId(materialId);
            const material = materialById(normalizedMaterialId);
            if (!material) return '';
            const invalid = operation.materialErrors?.[normalizedMaterialId] ? ' is-invalid' : '';
            return '<div class="material-row' + invalid + '" data-material-id="' + normalizedMaterialId + '" data-material-key="' + escapeAttr(materialId) + '"><div><div class="material-title">' + escapeHtml(material.title) + '</div><div class="material-code">' + escapeHtml(material.code || 'بدون کد') + '</div></div><input class="form-control material-qty" type="number" min="0.0001" step="any" value="' + escapeAttr(qty) + '" aria-label="مقدار ' + escapeAttr(material.title) + '"><div class="material-unit">' + escapeHtml(material.unit || '—') + '</div><button type="button" class="btn btn-sm btn-outline-danger remove-material" aria-label="حذف ' + escapeAttr(material.title) + '"><i class="bi bi-trash3"></i></button><div class="material-error ' + (operation.materialErrors?.[normalizedMaterialId] ? '' : 'd-none') + '">' + escapeHtml(operation.materialErrors?.[normalizedMaterialId] || '') + '</div></div>';
        }).join('');
        empty.classList.toggle('d-none', Object.keys(operation.materials).length > 0);
        list.querySelectorAll('.material-row').forEach(row => {
            const materialKey = row.dataset.materialKey;
            row.querySelector('.material-qty').addEventListener('input', event => { operation.materials[materialKey] = event.target.value; operation.materialErrors = {}; markModified(operation); renderOperationList(); renderAllSummary(); });
            row.querySelector('.remove-material').addEventListener('click', () => { delete operation.materials[materialKey]; markModified(operation); renderDetail(); renderOperationList(); renderAllSummary(); });
        });
    };

    const renderDetail = () => {
        const operation = activeOperation();
        if (!operation) { detailCards.innerHTML = ''; emptyDetail.classList.remove('d-none'); document.getElementById('mobile-active-operation').textContent = 'هنوز عملیاتی انتخاب نشده'; return; }
        emptyDetail.classList.add('d-none');
        const category = categoryById(operation.operation_category_id);
        const title = category?.dataset.title || operation.category_title || 'عملیات';
        const code = category?.dataset.code || operation.category_code || '—';
        const deleteAction = operation.changeStatus === 'pending_delete' ? '<button type="button" class="btn btn-outline-success" id="undo-active-operation">لغو حذف</button>' : '<button type="button" class="btn btn-outline-danger" id="delete-active-operation">حذف عملیات</button>';
        const fields = operation.changeStatus === 'pending_delete' ? '<div class="alert alert-warning">این عملیات برای حذف علامت‌گذاری شده است؛ برای بازگرداندن آن «لغو حذف» را بزنید.</div>' : '<input type="hidden" name="operations[' + escapeAttr(operation.client_key) + '][id]" value="' + escapeAttr(operation.operation_id || '') + '"><input type="hidden" name="operations[' + escapeAttr(operation.client_key) + '][version]" value="' + escapeAttr(operation.version || '') + '"><input type="hidden" name="operations[' + escapeAttr(operation.client_key) + '][operation_category_id]" value="' + escapeAttr(operation.operation_category_id) + '"><div class="row g-3"><div class="col-md-4"><label class="form-label">تاریخ عملیات</label><input class="form-control operation-date" type="date" value="' + escapeAttr(operation.operation_date || '') + '" required></div><div class="col-md-8"><label class="form-label">شرح مستقل عملیات</label><textarea class="form-control operation-description" rows="3" maxlength="10000">' + escapeHtml(operation.description || '') + '</textarea></div></div><div class="materials-box mt-4"><div class="materials-heading"><div><strong>مصالح مصرفی</strong><small>مصالح این عملیات مستقل است.</small></div><button type="button" class="btn btn-outline-primary add-material"><i class="bi bi-plus-lg"></i> افزودن مصالح</button></div><div class="materials-list"></div><div class="material-empty">هنوز مصالحی برای این عملیات انتخاب نشده است.</div></div><div class="field-error operation-error ' + (operation.error ? '' : 'd-none') + '">' + escapeHtml(operation.error || '') + '</div>';
        detailCards.innerHTML = '<article class="operation-card is-active' + (operation.changeStatus === 'pending_delete' ? ' is-invalid' : '') + '"><div class="operation-header"><div class="operation-heading"><span class="operation-number">' + fa([...state.keys()].indexOf(operation.client_key) + 1) + '</span><div><div class="operation-name">' + escapeHtml(title) + '</div><div class="operation-sub">کد ' + escapeHtml(code) + ' · ' + operationStatusLabel(operation) + '</div></div></div><div class="operation-actions">' + deleteAction + '</div></div><div class="operation-body">' + fields + '</div></article>';
        document.getElementById('mobile-active-operation').textContent = title;
        const card = detailCards.querySelector('.operation-card');
        card.querySelector('#delete-active-operation')?.addEventListener('click', () => markPendingDelete(operation.client_key));
        card.querySelector('#undo-active-operation')?.addEventListener('click', () => undoDelete(operation.client_key));
        if (operation.changeStatus !== 'pending_delete') {
            card.querySelector('.operation-date').addEventListener('input', event => { operation.operation_date = event.target.value; markModified(operation); renderOperationList(); renderAllSummary(); });
            card.querySelector('.operation-description').addEventListener('input', event => { operation.description = event.target.value; markModified(operation); renderOperationList(); renderAllSummary(); });
            card.querySelector('.add-material').addEventListener('click', () => { modalOperationKey = operation.client_key; openMaterialModal(); });
            renderMaterialRows(operation, card);
        }
    };

    const markPendingDelete = key => {
        const operation = state.get(String(key));
        if (!operation || operation.changeStatus === 'pending_delete') return;
        if (!window.confirm('این عملیات برای حذف علامت‌گذاری شود؟ حذف نهایی هنگام ذخیره انجام می‌شود.')) return;
        operation.previousChangeStatus = operation.changeStatus;
        operation.changeStatus = 'pending_delete';
        activeOperationKey = [...state.values()].find(item => item.changeStatus !== 'pending_delete')?.client_key || null;
        renderAll();
    };
    const undoDelete = key => {
        const operation = state.get(String(key));
        if (!operation) return;
        operation.changeStatus = operation.previousChangeStatus || (operation.operation_id ? 'unchanged' : 'new');
        operation.previousChangeStatus = null;
        activeOperationKey = activeOperationKey || key;
        renderAll();
    };

    const renderAllSummary = () => {
        const operations = visibleOperations();
        const kinds = new Set();
        let total = 0;
        operations.forEach(operation => Object.entries(operation.materials).forEach(([id, qty]) => { if (materialById(id)) kinds.add(id); if (Number(qty) > 0) total += Number(qty); }));
        document.getElementById('selected-count').textContent = fa(operations.length) + ' عملیات';
        document.getElementById('dock-operation-count').textContent = fa(operations.length);
        document.getElementById('dock-material-count').textContent = fa(kinds.size);
        document.getElementById('dock-material-qty').textContent = fa(total);
        document.getElementById('selected-empty').classList.toggle('d-none', operations.length > 0);
        document.getElementById('preview-list').innerHTML = operations.length ? operations.map(operation => '<span class="preview-chip">' + escapeHtml(categoryById(operation.operation_category_id)?.dataset.title || operation.category_title || 'عملیات') + ' ' + operationStatusLabel(operation) + '</span>').join('') : '<span class="text-muted small">هنوز عملیاتی انتخاب نشده است.</span>';
        const changedCount = [...state.values()].filter(operation => operation.changeStatus !== 'unchanged').length;
        document.getElementById('unsaved-change-count').textContent = changedCount ? 'تغییر ذخیره‌نشده: ' + fa(changedCount) : '';
        document.getElementById('dock-error-stat').classList.toggle('d-none', !operations.some(operation => operation.error || Object.keys(operation.materialErrors || {}).length));
    };
    const renderAll = () => { renderOperationList(); renderCatalog(); renderDetail(); renderAllSummary(); };

    const validateMaterialState = operations => {
        let valid = true;
        let firstInvalidKey = null;
        operations.forEach(operation => {
            const { duplicates } = materialEntries(operation);
            if (!duplicates.size) return;
            valid = false;
            firstInvalidKey = firstInvalidKey || operation.client_key;
            duplicates.forEach((values, materialId) => {
                operation.materialErrors[materialId] = materialDuplicateMessage(materialId);
            });
            operation.error = 'برای این عملیات مصالح تکراری وجود دارد؛ ردیف‌های مشخص‌شده را بررسی کنید.';
        });
        if (!valid) activeOperationKey = firstInvalidKey;
        return valid;
    };

    const openMaterialModal = () => {
        const operation = state.get(String(modalOperationKey));
        if (!operation) return;
        document.getElementById('material-search').value = '';
        document.getElementById('material-category').value = '';
        renderMaterialModal();
        modal.classList.add('is-open');
        document.getElementById('material-search').focus();
    };
    const renderMaterialModal = () => {
        const operation = state.get(String(modalOperationKey));
        if (!operation) return;
        const term = normalize(document.getElementById('material-search').value);
        const category = document.getElementById('material-category').value;
        const selected = new Set(Object.keys(operation.materials).map(normalizeMaterialId));
        const visible = catalog.filter(item => (!category || String(item.category_id) === category) && (!term || normalize((item.title || '') + ' ' + (item.code || '')).includes(term)));
        document.getElementById('material-modal-list').innerHTML = visible.length ? visible.map(item => '<label class="material-choice ' + (selected.has(String(item.id)) ? 'is-disabled' : '') + '"><input type="checkbox" value="' + item.id + '" ' + (selected.has(String(item.id)) ? 'checked disabled' : '') + '><span><strong>' + escapeHtml(item.title) + '</strong><small class="d-block text-muted"><span dir="ltr">' + escapeHtml(item.code || 'بدون کد') + '</span> · ' + escapeHtml(item.unit || '—') + '</small></span></label>').join('') : '<div class="selected-empty">مصالحی با این جست‌وجو پیدا نشد.</div>';
    };
    const closeMaterialModal = () => { modal.classList.remove('is-open'); modalOperationKey = null; };
    const addSelectedMaterials = () => {
        const operation = state.get(String(modalOperationKey));
        if (!operation) return;
        const applyButton = document.getElementById('apply-material-modal');
        if (applyButton.disabled) return;
        applyButton.disabled = true;
        document.querySelectorAll('#material-modal-list input[type="checkbox"]:checked:not(:disabled)').forEach(input => {
            const materialId = normalizeMaterialId(input.value);
            if (!Object.keys(operation.materials).some(key => normalizeMaterialId(key) === materialId)) {
                operation.materials[materialId] = '';
                markModified(operation);
            }
        });
        closeMaterialModal();
        renderAll();
        applyButton.disabled = false;
    };

    const createOperation = option => {
        const key = newClientKey();
        const operation = { client_key:key, operation_id:null, operation_category_id:Number(option.dataset.categoryId), category_title:option.dataset.title, category_code:option.dataset.code, operation_date:new Date().toISOString().slice(0,10), description:'', materials:{}, version:null, changeStatus:'new', previousChangeStatus:null, error:'', materialErrors:{} };
        state.set(key, operation);
        activeOperationKey = key;
        renderAll();
        closeOperationSheet();
    };

    const bindEvents = () => {
        existingList.addEventListener('click', event => {
            const undo = event.target.closest('.undo-operation');
            if (undo) { event.stopPropagation(); undoDelete(undo.dataset.operationKey); return; }
            const item = event.target.closest('.operation-option');
            if (item) setActiveOperation(item.dataset.operationKey);
        });
        catalogList.addEventListener('click', event => { const option = event.target.closest('.operation-catalog-option'); if (option && !option.disabled) createOperation(option); });
        document.getElementById('operation-search').addEventListener('input', event => {
            const term = normalize(event.target.value);
            document.querySelectorAll('#existing-operation-list .operation-option').forEach(item => { item.hidden = !!term && !normalize(item.dataset.search || '').includes(term); });
            document.querySelectorAll('#operation-options .operation-catalog-option').forEach(item => { item.hidden = !!term && !normalize(item.dataset.search || '').includes(term); });
        });
        document.getElementById('add-operation-button').addEventListener('click', () => { openOperationSheet(); document.getElementById('operation-search').focus(); });
        document.getElementById('open-operation-sheet').addEventListener('click', openOperationSheet);
        document.getElementById('close-operation-sheet').addEventListener('click', closeOperationSheet);
        document.getElementById('back-to-operation-list').addEventListener('click', openOperationSheet);
        document.getElementById('close-material-modal').addEventListener('click', closeMaterialModal);
        document.getElementById('cancel-material-modal').addEventListener('click', closeMaterialModal);
        modal.addEventListener('click', event => { if (event.target === modal) closeMaterialModal(); });
        document.getElementById('material-modal-list').addEventListener('click', event => {
            const choice = event.target.closest('.material-choice.is-disabled');
            if (!choice) return;
            const input = choice.querySelector('input[type="checkbox"]');
            const operationKey = modalOperationKey;
            const materialId = normalizeMaterialId(input?.value);
            event.preventDefault();
            closeMaterialModal();
            activeOperationKey = operationKey;
            renderDetail();
            [...detailCards.querySelectorAll('.material-row')].find(row => row.dataset.materialId === materialId)?.querySelector('.material-qty')?.focus();
        });
        document.getElementById('material-search').addEventListener('input', renderMaterialModal);
        document.getElementById('material-category').addEventListener('change', renderMaterialModal);
        document.getElementById('apply-material-modal').addEventListener('click', addSelectedMaterials);
        window.addEventListener('beforeunload', event => { if (dirty()) { event.preventDefault(); event.returnValue = ''; } });
        form.addEventListener('submit', submitForm);
    };

    const submitForm = async event => {
        event.preventDefault();
        const operations = visibleOperations();
        const deleted = [...state.values()].filter(operation => operation.changeStatus === 'pending_delete' && operation.operation_id);
        if (!operations.length) { setMessage('حداقل یک عملیات را نگه دارید.', 'danger'); return; }
        const button = document.getElementById('submit-batch');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm ms-1"></span> در حال ذخیره…';
        state.forEach(operation => { operation.error = ''; operation.materialErrors = {}; });
        if (!validateMaterialState(operations)) {
            renderAll();
            setMessage('مصالح تکراری در فرم وجود دارد؛ ابتدا ردیف‌های مشخص‌شده را اصلاح کنید.', 'danger');
            button.disabled = false;
            button.textContent = editing ? 'ذخیره تغییرات' : 'ثبت تعمیرات';
            return;
        }
        const formData = new FormData();
        formData.append('_token', form.querySelector('input[name="_token"]').value);
        if (editing) formData.append('_method', 'PUT');
        if (!editing) formData.append('client_uuid', form.querySelector('input[name="client_uuid"]').value);
        const submittedMaterialIds = new Map();
        operations.forEach(operation => {
            const key = operation.client_key;
            if (operation.operation_id) formData.append('operations[' + key + '][id]', operation.operation_id);
            if (operation.version) formData.append('operations[' + key + '][version]', operation.version);
            formData.append('operations[' + key + '][operation_category_id]', operation.operation_category_id);
            formData.append('operations[' + key + '][operation_date]', operation.operation_date);
            formData.append('operations[' + key + '][description]', operation.description || '');
            const entries = materialEntries(operation).entries;
            submittedMaterialIds.set(key, entries.map(entry => entry.materialId));
            entries.forEach((entry, index) => {
                formData.append('operations[' + key + '][materials][' + index + '][material_id]', entry.materialId);
                formData.append('operations[' + key + '][materials][' + index + '][qty]', entry.qty);
            });
        });
        deleted.forEach(operation => formData.append('deleted_operation_ids[]', operation.operation_id));
        try {
            const response = await fetch(form.action, { method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':form.querySelector('input[name="_token"]').value}, body:formData });
            const result = await response.json();
            if (!response.ok) {
                Object.entries(result.errors || {}).forEach(([key, values]) => {
                    const match = key.match(/^operations\.([^\.]+)/);
                    const operation = match ? state.get(String(match[1])) : null;
                    if (!operation) return;
                    operation.error = (Array.isArray(values) ? values : [values]).join(' ');
                    const materialMatch = key.match(/materials\.(\d+)\.(material_id|qty)$/);
                    if (materialMatch) {
                        const materialId = submittedMaterialIds.get(operation.client_key)?.[Number(materialMatch[1])] || materialMatch[1];
                        operation.materialErrors[normalizeMaterialId(materialId)] = operation.error;
                    }
                });
                renderAll();
                throw new Error(result.message || 'ذخیره تغییرات انجام نشد؛ خطاهای فرم را بررسی کنید.');
            }
            setMessage(editing ? 'تغییرات با موفقیت ذخیره شد.' : 'عملیات با موفقیت ثبت شد.', 'success');
            setTimeout(() => { window.location.href = result.redirect_url || {{ \Illuminate\Support\Js::from(url('/riser/'.$maintenanceCase->riser_id)) }}; }, 500);
        } catch (error) {
            setMessage(error.message || 'خطای شبکه؛ دوباره تلاش کنید.', 'danger');
            button.disabled = false;
            button.innerHTML = editing ? '<i class="bi bi-check2-circle"></i> ذخیره همه تغییرات' : '<i class="bi bi-check2-circle"></i> ثبت همه تعمیرات';
        }
    };

    initialOperations.forEach(existing => {
        const key = 'operation-' + existing.id;
        state.set(key, { client_key:key, operation_id:Number(existing.id), operation_category_id:Number(existing.operation_category_id), category_title:existing.category_title || '', category_code:existing.category_code || '', operation_date:existing.operation_date || '', description:existing.description || '', materials:Object.assign({}, existing.materials || {}), version:existing.version || null, changeStatus:'unchanged', previousChangeStatus:null, error:'', materialErrors:{} });
    });
    activeOperationKey = [...state.keys()][0] || null;
    bindEvents();
    renderAll();
})();
</script>
@endpush
