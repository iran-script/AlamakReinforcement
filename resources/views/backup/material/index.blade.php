@extends('layouts.app')

@section('title','مصالح')

@section('content')

<div class="container-fluid">

    @if(session('success'))

        <div class="alert alert-success">

            {{ session('success') }}

        </div>

    @endif

    <div class="card shadow">

        <div class="card-header d-flex justify-content-between align-items-center">

            <h5 class="mb-0">

                لیست مصالح

            </h5>

            <a href="{{ route('material.create') }}"
               class="btn btn-primary">

                <i class="bi bi-plus-circle"></i>

                افزودن مصالح

            </a>

        </div>

        <div class="card-body">

            <form method="GET">

                <div class="row mb-3">

                    <div class="col-md-4">

                        <input
                            type="text"
                            name="search"
                            value="{{ request('search') }}"
                            class="form-control"
                            placeholder="جستجو...">

                    </div>

                    <div class="col-md-3">

                        <button class="btn btn-secondary">

                            جستجو

                        </button>

                    </div>

                </div>

            </form>

            <table class="table table-bordered table-hover align-middle">

                <thead class="table-light">

                    <tr>

                        <th width="60">

                            #

                        </th>

                        <th>

                            دسته بندی

                        </th>

                        <th>

                            نام مصالح

                        </th>

                        <th>

                            کد کالا

                        </th>

                        <th width="100">

                            واحد

                        </th>

                        <th width="120">

                            قیمت

                        </th>

                        <th width="100">

                            وضعیت

                        </th>

                        <th width="180">

                            عملیات

                        </th>

                    </tr>

                </thead>

                <tbody>

                @forelse($items as $item)

                    <tr>

                        <td>

                            {{ $loop->iteration }}

                        </td>

                        <td>

                            {{ $item->category?->title }}

                        </td>

                        <td>

                            {{ $item->title }}

                        </td>

                        <td>

                            {{ $item->code }}

                        </td>

                        <td>

                            {{ $item->unit }}

                        </td>

                        <td>

                            {{ number_format($item->default_price) }}

                        </td>

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

                            <a href="{{ route('material.edit',$item) }}"
                               class="btn btn-warning btn-sm">

                                ویرایش

                            </a>

                            <form
                                action="{{ route('material.destroy',$item) }}"
                                method="POST"
                                class="d-inline">

                                @csrf

                                @method('DELETE')

                                <button
                                    class="btn btn-danger btn-sm"
                                    onclick="return confirm('حذف شود؟')">

                                    حذف

                                </button>

                            </form>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="8"
                            class="text-center">

                            اطلاعاتی یافت نشد.

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