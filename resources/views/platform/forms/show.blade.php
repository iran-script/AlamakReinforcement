@extends('layouts.app')
@section('title', $formDefinition->name)
@include('platform.forms._assets')
@section('content')
<main class="form-studio" data-form-studio data-definition-detail data-definition-id="{{ $formDefinition->id }}">
    <header class="fs-page-head"><div><span class="fs-kicker">{{ $formDefinition->key }}</span><h1>{{ $formDefinition->name }}</h1><p>{{ $formDefinition->description ?: 'بدون توضیح' }}</p><div class="fs-meta"><span>{{ $formDefinition->organization_id ? 'فرم سازمانی' : 'الگوی عمومی' }}</span><span>{{ $formDefinition->resource_key ?: $formDefinition->subject_type ?: 'منبع عمومی' }}</span></div></div><div class="fs-actions"><a class="fs-btn" href="{{ route('platform.forms.index') }}">فهرست فرم‌ها</a>@can('update',$formDefinition)<a class="fs-btn" href="{{ route('platform.forms.edit',$formDefinition) }}">ویرایش مشخصات</a><button class="fs-btn fs-btn-primary" data-create-version>نسخه پیش‌نویس جدید</button>@endcan</div></header>
    <div class="fs-alert fs-alert-info"><strong>قاعده نسخه‌ها:</strong> نسخه منتشرشده تغییرناپذیر است. برای تغییر، آن را به یک پیش‌نویس جدید تکثیر کنید.</div><div class="fs-alert fs-alert-danger" data-errors hidden></div>
    <section class="fs-panel"><div class="fs-panel-head"><h2>تاریخچه نسخه‌ها</h2><span>{{ $formDefinition->versions->count() }} نسخه</span></div>
    @forelse($formDefinition->versions as $version)<article class="fs-version-row"><div class="fs-version-number">نسخه <strong>{{ $version->version }}</strong></div><div><span class="fs-badge fs-{{ $version->status->value }}">{{ ['draft'=>'پیش‌نویس','published'=>'منتشرشده','archived'=>'بایگانی‌شده'][$version->status->value] }}</span><p>{{ $version->fields_count }} فیلد · ایجاد {{ $version->created_at->diffForHumans() }}@if($version->published_at) · انتشار توسط {{ $version->publisher?->name ?: 'سامانه' }}@endif</p></div><div class="fs-actions">
        <a class="fs-btn fs-btn-sm" href="{{ route('platform.forms.designer',$version) }}">{{ $version->status === App\Enums\FormVersionStatus::Draft ? 'بازکردن طراح' : 'مشاهده' }}</a><a class="fs-btn fs-btn-sm" href="{{ route('platform.forms.preview',$version) }}">پیش‌نمایش</a>
        @can('publish',$version)@if($version->status === App\Enums\FormVersionStatus::Published)<button class="fs-btn fs-btn-sm" data-clone-version="{{ $version->id }}">تکثیر به پیش‌نویس</button><button class="fs-btn fs-btn-sm fs-btn-danger" data-archive-version="{{ $version->id }}">بایگانی</button>@endif @endcan
    </div></article>@empty<div class="fs-empty"><h2>این فرم هنوز نسخه‌ای ندارد</h2><p>یک نسخه پیش‌نویس بسازید و طراحی را آغاز کنید.</p></div>@endforelse</section>
</main>
@endsection
