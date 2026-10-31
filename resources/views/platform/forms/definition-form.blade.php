@extends('layouts.app')
@php($editing = isset($formDefinition))
@section('title', $editing ? 'ویرایش فرم' : 'فرم جدید')
@include('platform.forms._assets')
@section('content')
<main class="form-studio" data-form-studio data-definition-editor data-definition-id="{{ $formDefinition->id ?? '' }}">
    <header class="fs-page-head"><div><span class="fs-kicker">FORM DEFINITION</span><h1>{{ $editing ? 'ویرایش تعریف فرم' : 'ساخت فرم جدید' }}</h1><p>مشخصات پایدار فرم را تعیین کنید؛ طراحی فیلدها در مرحله بعد انجام می‌شود.</p></div><a class="fs-btn" href="{{ $editing ? route('platform.forms.show',$formDefinition) : route('platform.forms.index') }}">بازگشت</a></header>
    <div class="fs-alert fs-alert-danger" data-errors hidden role="alert"></div>
    <form class="fs-panel fs-definition-form" data-definition-form novalidate>
        <div class="fs-grid-2"><label><span>نام فرم *</span><input name="name" required maxlength="255" value="{{ $formDefinition->name ?? '' }}"></label><label><span>کلید فرم *</span><input name="key" required maxlength="80" pattern="[a-z][a-z0-9_-]*" dir="ltr" @readonly($editing) value="{{ $formDefinition->key ?? '' }}"><small>حروف انگلیسی کوچک، عدد، خط تیره و زیرخط</small></label></div>
        <label><span>توضیحات</span><textarea name="description" rows="4" maxlength="5000">{{ $formDefinition->description ?? '' }}</textarea></label>
        <div class="fs-grid-2"><label><span>کلید منبع</span><input name="resource_key" maxlength="100" dir="ltr" value="{{ $formDefinition->resource_key ?? '' }}" placeholder="work_orders"></label><label><span>نوع موضوع</span><input name="subject_type" maxlength="100" dir="ltr" value="{{ $formDefinition->subject_type ?? '' }}" placeholder="work_orders"></label></div>
        <label class="fs-switch"><input type="checkbox" name="is_active" @checked(!isset($formDefinition) || $formDefinition->is_active)><span>فرم فعال باشد</span></label>
        @if(auth()->user()->hasRole('superadmin') && !$editing)<label><span>شناسه سازمان</span><input type="number" min="1" name="organization_id"><small>برای ساخت الگوی عمومی خالی بگذارید.</small></label>@endif
        <footer class="fs-form-actions"><button class="fs-btn fs-btn-primary" type="submit" data-submit>{{ $editing ? 'ذخیره تغییرات' : 'ساخت فرم' }}</button><span class="fs-status" data-status aria-live="polite"></span></footer>
    </form>
</main>
@endsection
