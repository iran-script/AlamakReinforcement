@extends('layouts.app')

@section('title','مدیریت نقش ها')

@section('content')

<div class="page-eyebrow">ROLES</div>
<div class="page-heading">
    <h4><i class="bi bi-shield-check text-primary"></i> مدیریت نقش‌ها</h4>

    <a href="{{ route('roles.create') }}" class="btn btn-success">
        <i class="bi bi-plus-circle"></i>
        نقش جدید
    </a>
</div>

<div class="card-box">

    <div class="card-body p-3">

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">

                <thead>
                    <tr>
                        <th width="70">ID</th>
                        <th>نام نقش</th>
                        <th width="140">عملیات</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($roles as $role)
                        <tr>
                            <td>{{ $role->id }}</td>
                            <td>{{ $role->name }}</td>
                            <td>
                                <a href="{{ route('roles.edit',$role) }}" class="btn btn-warning btn-sm">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                @if($role->name != 'superadmin')
                                    <form method="POST" action="{{ route('roles.destroy',$role) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')

                                        <button class="btn btn-danger btn-sm" onclick="return confirm('حذف شود؟')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    نقشی ثبت نشده است
                                </div>
                            </td>
                        </tr>
                    @endforelse

                </tbody>

            </table>
        </div>

        <div class="mt-3">
            {{ $roles->links() }}
        </div>

    </div>

</div>

@endsection
