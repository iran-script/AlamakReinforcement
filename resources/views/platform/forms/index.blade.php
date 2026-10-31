@extends('layouts.app')
@section('title', 'مدیریت فرم‌ها')
@include('platform.forms._assets')
@section('content')
<main class="form-studio" data-form-studio>
    <header class="fs-page-head">
        <div><span class="fs-kicker">FORM STUDIO</span><h1>مدیریت فرم‌ها</h1><p>فرم‌های سازمانی، نسخه‌ها و وضعیت انتشار را مدیریت کنید.</p></div>
        @can('create', App\Models\FormDefinition::class)<a class="fs-btn fs-btn-primary" href="{{ route('platform.forms.create') }}"><i class="bi bi-plus-lg"></i> فرم جدید</a>@endcan
    </header>
    <form class="fs-filter" method="GET" aria-label="فیلتر فرم‌ها">
        <label><span>جستجو</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="نام یا کلید فرم"></label>
        <label><span>وضعیت</span><select name="active"><option value="">همه</option><option value="1" @selected(($filters['active'] ?? '') === '1')>فعال</option><option value="0" @selected(($filters['active'] ?? '') === '0')>غیرفعال</option></select></label>
        <label><span>محدوده</span><select name="scope"><option value="">همه</option><option value="organization" @selected(($filters['scope'] ?? '') === 'organization')>سازمانی</option><option value="global" @selected(($filters['scope'] ?? '') === 'global')>عمومی</option></select></label>
        <label><span>منبع</span><input name="resource_key" value="{{ $filters['resource_key'] ?? '' }}" placeholder="work_orders"></label>
        <button class="fs-btn" type="submit">اعمال فیلتر</button>
    </form>
    <section class="fs-panel">
        @if($definitions->isEmpty())
            <div class="fs-empty"><i class="bi bi-ui-checks-grid"></i><h2>هنوز فرمی ساخته نشده است</h2><p>برای شروع، یک تعریف فرم جدید ایجاد کنید.</p>@can('create', App\Models\FormDefinition::class)<a class="fs-btn fs-btn-primary" href="{{ route('platform.forms.create') }}">ساخت اولین فرم</a>@endcan</div>
        @else
        <div class="fs-table-wrap"><table class="fs-table"><thead><tr><th>فرم</th><th>منبع</th><th>نسخه جاری</th><th>محدوده</th><th>به‌روزرسانی</th><th>عملیات</th></tr></thead><tbody>
        @foreach($definitions as $definition)
            @php($published = $definition->versions->firstWhere('status.value', 'published') ?? $definition->versions->firstWhere('status', App\Enums\FormVersionStatus::Published))
            @php($latest = $definition->versions->first())
            <tr><td data-label="فرم"><strong>{{ $definition->name }}</strong><code>{{ $definition->key }}</code>@unless($definition->is_active)<span class="fs-badge fs-muted">غیرفعال</span>@endunless</td>
                <td data-label="منبع">{{ $definition->resource_key ?: $definition->subject_type ?: 'عمومی' }}</td>
                <td data-label="نسخه جاری">@if($published)<span class="fs-badge fs-published">منتشرشده · {{ $published->version }}</span>@elseif($latest)<span class="fs-badge fs-draft">پیش‌نویس · {{ $latest->version }}</span>@else<span class="fs-badge fs-muted">بدون نسخه</span>@endif</td>
                <td data-label="محدوده">{{ $definition->organization_id ? 'سازمانی' : 'الگوی عمومی' }}</td><td data-label="به‌روزرسانی">{{ $definition->updated_at?->diffForHumans() }}</td>
                <td data-label="عملیات"><div class="fs-actions"><a class="fs-btn fs-btn-sm" href="{{ route('platform.forms.show', $definition) }}">مشاهده</a>@can('update',$definition)<a class="fs-btn fs-btn-sm" href="{{ route('platform.forms.edit',$definition) }}">ویرایش</a>@endcan</div></td></tr>
        @endforeach</tbody></table></div><div class="fs-pagination">{{ $definitions->links() }}</div>
        @endif
    </section>
</main>
@endsection
