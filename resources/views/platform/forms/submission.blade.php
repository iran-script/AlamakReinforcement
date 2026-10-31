@extends('layouts.app')
@section('title', 'مشاهده ارسال فرم')
@include('platform.forms._assets')
@section('content')
<main class="form-studio" data-form-studio>
    <header class="fs-page-head"><div><span class="fs-kicker">SUBMISSION #{{ $formSubmission->id }}</span><h1>{{ $formSubmission->version->definition->name }}</h1><p>نسخه دقیق {{ $formSubmission->version->version }} · ارسال غیرقابل‌تغییر</p></div><a class="fs-btn" href="{{ route('platform.forms.show',$formSubmission->version->definition) }}">مشاهده فرم</a></header>
    <section class="fs-submission-meta"><div><span>وضعیت</span><strong class="fs-badge fs-{{ $formSubmission->status->value }}">{{ ['draft'=>'پیش‌نویس','submitted'=>'ارسال‌شده','superseded'=>'جایگزین‌شده','cancelled'=>'لغوشده'][$formSubmission->status->value] }}</strong></div><div><span>ارسال‌کننده</span><strong>{{ $formSubmission->submitter?->name ?: 'ثبت نشده' }}</strong></div><div><span>زمان ارسال</span><strong>{{ $formSubmission->submitted_at?->format('Y/m/d H:i') ?: 'هنوز ارسال نشده' }}</strong></div><div><span>موضوع</span><strong>{{ $formSubmission->subject_type ?: 'بدون پیوند' }} {{ $formSubmission->subject_id }}</strong></div></section>
    <section class="fs-panel fs-submission-values"><div class="fs-panel-head"><h2>مقادیر ثبت‌شده</h2></div>@foreach($fields as $field)<div class="fs-value-row"><div><strong>{{ $field->label }}</strong><code>{{ $field->key }}</code></div><div>@php($value=$formSubmission->data[$field->key]??null)@if(is_array($value))<pre>{{ json_encode($value,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>@elseif(is_bool($value)){{ $value?'بله':'خیر' }}@else{{ $value ?? '—' }}@endif
        @foreach($files->where('field_key',$field->key) as $file)<a class="fs-attachment" href="{{ route('platform.forms.files.download',[$formSubmission,$file]) }}"><i class="bi bi-paperclip"></i><span>{{ $file->original_name }}</span><small>{{ number_format($file->size/1024,1) }} KB</small></a>@endforeach</div></div>@endforeach</section>
</main>
@endsection
