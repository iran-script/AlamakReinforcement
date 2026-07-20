@extends('layouts.app')

@section('title','مصالح')

@section('content')

<div class="page-eyebrow">MATERIALS</div>
<div class="page-heading">
    <h4><i class="bi bi-box-seam text-primary"></i> لیست مصالح</h4>

    <a href="{{ route('material.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i>
        افزودن مصالح
    </a>
</div>

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

<div class="card-box">

    <div class="card-body p-3">

        <form method="GET" class="mb-3">
            <div class="row g-2">
                <div class="col-md-4">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        class="form-control"
                        placeholder="جستجو...">
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
                        <th>دسته بندی</th>
                        <th>نام مصالح</th>
                        <th>کد کالا</th>
                        <th width="100">واحد</th>
                        <th width="120">قیمت</th>
                        <th width="100">وضعیت</th>
                        <th width="180">عملیات</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item->category?->title }}</td>
                            <td>{{ $item->title }}</td>
                            <td>{{ $item->code }}</td>
                            <td>{{ $item->unit }}</td>
                            <td>{{ number_format($item->default_price) }}</td>
                            <td>
                                @if($item->is_active)
                                    <span class="status-pill status-done">فعال</span>
                                @else
                                    <span class="status-pill status-reject">غیرفعال</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('material.edit',$item) }}" class="btn btn-warning btn-sm">
                                    <i class="bi bi-pencil"></i>
                                    ویرایش
                                </a>

                                <form action="{{ route('material.destroy',$item) }}" method="POST" class="d-inline">
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
                            <td colspan="8">
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
