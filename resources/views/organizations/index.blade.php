@extends('layouts.app')
@section('title', 'مدیریت سازمان‌ها')
@section('content')
<div class="page-heading d-flex justify-content-between align-items-center"><div><div class="page-eyebrow">SYSTEM / ORGANIZATIONS</div><h4><i class="bi bi-building text-primary"></i> مدیریت سازمان‌ها</h4></div><a class="btn btn-primary" href="{{ route('organizations.create') }}"><i class="bi bi-plus-lg"></i> سازمان جدید</a></div>
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
<div class="card-box"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>کد</th><th>نام سازمان</th><th>وضعیت</th><th>Zone</th><th>گروه کاربران</th><th>کاربران</th><th>عملیات</th></tr></thead><tbody>
@forelse($organizations as $organization)<tr><td dir="ltr"><code>{{ $organization->code }}</code></td><td>{{ $organization->name }}</td><td><span class="badge {{ $organization->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ $organization->status === 'active' ? 'فعال' : 'غیرفعال' }}</span></td><td>{{ $organization->zones_count }}</td><td>{{ $organization->group_users_count }}</td><td>{{ $organization->users_count }}</td><td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('organizations.edit', $organization) }}">ویرایش</a> <form class="d-inline" method="POST" action="{{ route('organizations.destroy', $organization) }}" onsubmit="return confirm('حذف این سازمان فقط در صورت نداشتن وابستگی ممکن است. ادامه می‌دهید؟');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">حذف</button></form></td></tr>
@empty<tr><td colspan="7" class="text-center text-muted py-4">هنوز سازمانی ثبت نشده است.</td></tr>@endforelse
</tbody></table></div><div class="p-3">{{ $organizations->links() }}</div></div>
@endsection
