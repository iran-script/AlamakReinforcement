@extends('layouts.app')

@section('title', 'مدیریت کاربران')

@section('content')

    <div class="card-box">

        ```
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">مدیریت کاربران</h3>

            <a href="{{ route('users.create') }}" class="btn btn-primary">
                <i class="bi bi-person-plus"></i>
                افزودن کاربر
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}

                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 70px;">#</th>
                        <th>نام</th>
                        <th>نام کاربری</th>
                        <th>ایمیل</th>
                        <th>نقش</th>
                        <th>زون</th>
                        <th style="width: 130px;">عملیات</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $users->firstItem() + $loop->index }}</td>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->username }}</td>
                            <td>{{ $user->email }}</td>

                            <td>
                                
                                @if ($user->roles)
                                    <span class="badge bg-primary">
                                        {{ $user->roles[0]->name }}
                                    </span>
                                @else
                                    <span class="text-muted">
                                        بدون نقش
                                    </span>
                                @endif
                            </td>

                            <td>
                                {{ $user->zone?->name ?? 'تعیین نشده' }}
                            </td>

                            <td class="text-nowrap">
                                <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-warning"
                                    title="ویرایش کاربر" data-bs-toggle="tooltip">
                                    <i class="bi bi-pencil-square"></i>
                                </a>

                                @if ($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('users.destroy', $user) }}" class="d-inline"
                                        onsubmit="return confirm('از حذف کاربر {{ $user->username }} مطمئن هستید؟');">
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="btn btn-sm btn-danger" title="حذف کاربر"
                                            data-bs-toggle="tooltip">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                هیچ کاربری ثبت نشده است.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $users->links() }}
        </div>
        ```

    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(element) {
                new bootstrap.Tooltip(element);
            });
        });
    </script>
@endpush
