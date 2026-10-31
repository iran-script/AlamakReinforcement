@extends('layouts.app')
@section('title', 'پیش‌نمایش '.$formVersion->definition->name)
@include('platform.forms._assets')
@section('content')
<main class="form-studio" data-form-studio data-preview>
    <header class="fs-page-head"><div><span class="fs-kicker">PREVIEW · V{{ $formVersion->version }}</span><h1>پیش‌نمایش {{ $formVersion->definition->name }}</h1><p>این صفحه نمایشی است و هیچ داده عملیاتی ثبت نمی‌کند.</p></div><div class="fs-actions"><button class="fs-btn" data-mobile-preview aria-pressed="false">نمای موبایل</button><a class="fs-btn" href="{{ route('platform.forms.designer',$formVersion) }}">بازگشت به طراح</a></div></header>
    @if($formVersion->status === App\Enums\FormVersionStatus::Draft)<div class="fs-alert fs-alert-warning">پیش‌نمایش نسخه پیش‌نویس؛ این نسخه هنوز منتشر نشده است.</div>@endif
    <section class="fs-preview-frame" data-preview-frame><div class="fs-runtime-form"><div class="fs-runtime-head"><h2>{{ $formVersion->definition->name }}</h2><span class="fs-badge fs-{{ $formVersion->status->value }}">نسخه {{ $formVersion->version }}</span></div>
    @forelse($fields as $field)<div class="fs-runtime-field" data-preview-field data-key="{{ $field->key }}"><label>{{ $field->label }} @if($field->is_required)<b aria-label="الزامی">*</b>@endif</label>@if($field->description)<small>{{ $field->description }}</small>@endif
        @switch($field->field_type->value)
        @case('textarea')<textarea disabled placeholder="{{ $field->placeholder }}"></textarea>@break
        @case('select') @case('multi_select')<select disabled @if($field->field_type->value==='multi_select') multiple @endif><option>انتخاب کنید</option>@foreach($field->options??[] as $option)<option>{{ $option['label'] }}</option>@endforeach</select>@break
        @case('boolean')<label class="fs-switch"><input type="checkbox" disabled><span>بله / خیر</span></label>@break
        @case('radio') @case('checkbox_group')<div class="fs-choice-preview">@foreach($field->options??[] as $option)<label><input disabled type="{{ $field->field_type->value==='radio'?'radio':'checkbox' }}"> {{ $option['label'] }}</label>@endforeach</div>@break
        @case('file') @case('image') @case('signature')<div class="fs-upload-placeholder"><i class="bi bi-cloud-arrow-up"></i> محل بارگذاری {{ $field->label }}</div>@break
        @case('repeatable_group')<div class="fs-repeatable-preview">گروه تکرارشونده · حداکثر {{ $field->configuration['max_items']??20 }} ردیف</div>@break
        @default<input disabled type="{{ in_array($field->field_type->value,['number','decimal','date','datetime']) ? ($field->field_type->value==='datetime'?'datetime-local':($field->field_type->value==='decimal'?'number':$field->field_type->value)) : 'text' }}" placeholder="{{ $field->placeholder }}">@endswitch
    </div>@empty<div class="fs-empty">فیلدی برای نمایش وجود ندارد.</div>@endforelse
    <button class="fs-btn fs-btn-primary" disabled>ثبت اطلاعات (غیرفعال در پیش‌نمایش)</button></div></section>
</main>
@endsection
