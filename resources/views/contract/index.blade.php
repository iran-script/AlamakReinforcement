@extends('layouts.app')

@section('title', 'پیمان‌ها')

@section('content')

    <div class="page-eyebrow">CONTRACTS</div>

    <div class="page-heading">
        <h4>
            <i class="bi bi-file-earmark-text text-primary"></i>
            لیست پیمان‌ها
        </h4>

        <a href="{{ route('contract.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i>
            افزودن پیمان
        </a>
    </div>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="card-box">

        <div class="card-body p-3">

            <form method="GET" class="mb-3">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" name="search" value="{{ request('search') }}" class="form-control"
                            placeholder="جستجو بر اساس شماره پیمان، پیمانکار یا ناظر...">
                    </div>

                    <div class="col-md-2 d-grid">
                        <button class="btn btn-secondary">
                            <i class="bi bi-search"></i>
                            جستجو
                        </button>
                    </div>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0">

                    <thead>
                        <tr>
                            <th width="60">#</th>
                            <th>شماره پیمان</th>
                            <th>نام پیمانکار</th>
                            <th>نام ناظر</th>

                            <th>ثبت کننده</th>
                            <th>عملیات</th>
                        </tr>
                    </thead>

                    <tbody>

                        @forelse($items as $item)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $item->contract_number }}</td>
                                <td>{{ $item->contractor_name }}</td>
                                <td>{{ $item->supervisor?->name }}</td>
                                <td>
                                    {{ $item->creator?->name }}
                                </td>

                                <td>
                                    <a href="{{ route('contract.edit', $item) }}" class="btn btn-warning btn-sm">
                                        <i class="bi bi-pencil"></i>
                                        ویرایش
                                    </a>

                                    <form action="{{ route('contract.destroy', $item) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')

                                        <button class="btn btn-danger btn-sm" onclick="return confirm('حذف شود؟')">
                                            <i class="bi bi-trash"></i>
                                            حذف
                                        </button>
                                    </form>
                                </td>
                            </tr>

                        @empty

                            <tr>
                                <td colspan="5">
                                    <div class="empty-state">
                                        <i class="bi bi-inbox"></i>
                                        اطلاعاتی یافت نشد
                                    </div>
                                </td>
                            </tr>
                        @endforelse

                    </tbody>

                </table>
            </div>

            <div class="mt-3">
                {{ $items->links() }}
            </div>

        </div>

    </div>

@endsection
