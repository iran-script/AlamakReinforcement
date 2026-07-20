@extends('layouts.app')

@section('title','دسته بندی عملیات')

@section('content')

<div class="container-fluid">

    <div class="card shadow-sm">

        <div class="card-header d-flex justify-content-between align-items-center">

            <h5 class="mb-0">
                دسته بندی عملیات
            </h5>

            <a href="{{ route('operation-category.create') }}"
               class="btn btn-primary">

                <i class="bi bi-plus-circle"></i>

                افزودن

            </a>

        </div>

        <div class="card-body">

            @if(session('success'))

                <div class="alert alert-success">

                    {{ session('success') }}

                </div>

            @endif

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-light">

                <tr>

                    <th width="70">ردیف</th>

                    <th>عنوان</th>

                    <th>کد</th>

                    <th>ترتیب</th>

                    <th>وضعیت</th>

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

                                <span class="badge bg-success">

                                    فعال

                                </span>

                            @else

                                <span class="badge bg-danger">

                                    غیرفعال

                                </span>

                            @endif

                        </td>

                        <td>

                            <a href="{{ route('operation-category.edit',$item) }}"
                               class="btn btn-warning btn-sm">

                                ویرایش

                            </a>

                            <form
                                action="{{ route('operation-category.destroy',$item) }}"
                                method="POST"
                                class="d-inline">

                                @csrf

                                @method('DELETE')

                                <button
                                    onclick="return confirm('حذف شود؟')"
                                    class="btn btn-danger btn-sm">

                                    حذف

                                </button>

                            </form>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="6"
                            class="text-center">

                            اطلاعاتی وجود ندارد.

                        </td>

                    </tr>

                @endforelse

                </tbody>

            </table>

            {{ $items->links() }}

        </div>

    </div>

</div>

@endsection