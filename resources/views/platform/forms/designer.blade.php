@extends('layouts.app')
@section('title', 'طراح فرم · نسخه '.$formVersion->version)
@include('platform.forms._assets')
@section('content')
<main class="form-studio fs-designer-page" data-form-studio data-designer data-version-id="{{ $formVersion->id }}" data-editable="{{ $editable ? '1' : '0' }}">
    <header class="fs-designer-head"><div><a href="{{ route('platform.forms.show',$formVersion->definition) }}">{{ $formVersion->definition->name }}</a><h1>طراح فرم · نسخه {{ $formVersion->version }}</h1></div><div class="fs-actions"><span class="fs-unsaved" data-unsaved hidden>تغییرات ذخیره‌نشده</span><a class="fs-btn" href="{{ route('platform.forms.preview',$formVersion) }}">پیش‌نمایش</a>@if($editable)<button class="fs-btn" data-validate>اعتبارسنجی</button><button class="fs-btn fs-btn-primary" data-save>ذخیره پیش‌نویس</button><button class="fs-btn fs-btn-success" data-publish>انتشار</button>@else @can('publish',$formVersion) @if($formVersion->status === App\Enums\FormVersionStatus::Published)<button class="fs-btn fs-btn-primary" data-clone-version="{{ $formVersion->id }}">تکثیر به پیش‌نویس</button>@endif @endcan @endif</div></header>
    @unless($editable)<div class="fs-alert fs-alert-warning"><strong>حالت فقط‌خواندنی:</strong> این نسخه {{ $formVersion->status === App\Enums\FormVersionStatus::Published ? 'منتشر شده و تغییرناپذیر' : 'بایگانی شده' }} است. برای ویرایش، نسخه منتشرشده را به پیش‌نویس تکثیر کنید.</div>@endunless
    <div class="fs-alert fs-alert-danger" data-errors hidden role="alert"></div><div class="fs-alert fs-alert-success" data-success hidden aria-live="polite"></div>
    <nav class="fs-mobile-tabs" aria-label="بخش‌های طراح"><button data-panel-tab="palette">فیلدها</button><button class="active" data-panel-tab="canvas">بوم طراحی</button><button data-panel-tab="inspector">ویژگی‌ها</button></nav>
    <div class="fs-workspace">
        <aside class="fs-palette" data-panel="palette" aria-label="فهرست فیلدها"><div class="fs-panel-head"><h2>فیلدها</h2><span>برای افزودن کلیک کنید</span></div><div data-palette></div></aside>
        <section class="fs-canvas-panel active" data-panel="canvas"><div class="fs-canvas-toolbar"><span data-field-count></span><span>چیدمان تک‌ستونه</span></div><div class="fs-canvas" data-canvas aria-live="polite"></div></section>
        <aside class="fs-inspector" data-panel="inspector" aria-label="ویژگی‌های فیلد"><div class="fs-panel-head"><h2>ویژگی‌های فیلد</h2><span data-selected-label>فیلدی انتخاب نشده</span></div><div data-inspector></div></aside>
    </div>
    <script type="application/json" data-version-payload>@json(['id'=>$formVersion->id,'status'=>$formVersion->status->value,'fields'=>$formVersion->fields])</script>
</main>
@endsection
