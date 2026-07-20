@extends('layouts.app')

@section('title','دسته بندی مصالح')

@section('content')

<div class="page-eyebrow">MATERIAL CATEGORIES</div>
<div class="page-heading">
    <h4><i class="bi bi-tags text-primary"></i> دسته بندی مصالح</h4>

    <a href="{{ route('material-category.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i>
        افزودن
    </a>
</div>

<div class="card-box">

    <div class="card-body p-3">

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle mb-0">

                <thead>
                    <tr>
                        <th width="60">ردیف</th>
                        <th>عنوان</th>
                        <th>کد</th>
                        <th width="100">ترتیب</th>
                        <th width="120">وضعیت</th>
                        <th width="170">عملیات</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse($items as $item)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $item->title }}</td>
                            <td>{{ $item->code }}</td>
                            <td>{{ $item->sort }}</td>
                            <td>
                                @if($item->is_active)
                                    <span class="status-pill status-done">فعال</span>
                                @else
                                    <span class="status-pill status-reject">غیرفعال</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('material-category.edit',$item) }}" class="btn btn-warning btn-sm">
                                    <i class="bi bi-pencil"></i>
                                    ویرایش
                                </a>

                                <form method="POST" action="{{ route('material-category.destroy',$item) }}" class="d-inline">
                                    @csrf
                                    @method('DELETE')

                                    <button onclick="return confirm('حذف شود؟')" class="btn btn-danger btn-sm">
                                        <i class="bi bi-trash"></i>
                                        حذف
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <i class="bi bi-inbox"></i>
                                    اطلاعاتی وجود ندارد
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
