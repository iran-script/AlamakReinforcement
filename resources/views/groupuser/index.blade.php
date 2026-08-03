@extends('layouts.app')

@section('title', 'مدیریت گروه کاربران')

@section('content')

<div class="page-eyebrow">GROUPUSER</div>

<div class="page-heading">
    <h4>
        <i class="bi bi-people-fill text-primary"></i>
        مدیریت گروه کاربران
    </h4>

    <a href="{{ route('groupusers.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i>
        افزودن گروه
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



<div class="card-box">

    <div class="card-body p-3">

        <div class="table-responsive">

            <table class="table table-bordered table-hover align-middle mb-0">

                <thead>
                    <tr>
                        <th width="60">#</th>
                        <th>نام گروه</th>
                        <th>توضیحات</th>
                        <th>تعداد کاربران</th>
                        <th>تاریخ ایجاد</th>
                        <th width="130">عملیات</th>
                    </tr>
                </thead>


                <tbody>

                    @forelse($groupusers as $group)

                        <tr>

                            <td>
                                {{ $groupusers->firstItem() + $loop->index }}
                            </td>


                            <td>
                                <span class="fw-bold">
                                    {{ $group->name }}
                                </span>
                            </td>


                            <td>
                                {{ $group->description ?? '---' }}
                            </td>


                            <td>
                                <span class="badge bg-info">
                                    {{ $group->users_count ?? 0 }}
                                </span>
                            </td>


                            <td>
                                {{ $group->created_at?->format('Y-m-d') }}
                            </td>


                            <td class="text-nowrap">


                                <a href="{{ route('groupusers.edit',$group) }}"
                                   class="btn btn-sm btn-warning"
                                   title="ویرایش گروه"
                                   data-bs-toggle="tooltip">

                                    <i class="bi bi-pencil-square"></i>

                                </a>



                                <form method="POST"
                                      action="{{ route('groupusers.destroy',$group) }}"
                                      class="d-inline"
                                      onsubmit="return confirm('از حذف این گروه مطمئن هستید؟');">

                                    @csrf
                                    @method('DELETE')


                                    <button type="submit"
                                            class="btn btn-sm btn-danger"
                                            title="حذف گروه"
                                            data-bs-toggle="tooltip">

                                        <i class="bi bi-trash"></i>

                                    </button>

                                </form>


                            </td>

                        </tr>


                    @empty


                        <tr>

                            <td colspan="6">

                                <div class="empty-state">

                                    <i class="bi bi-people"></i>

                                    هیچ گروه کاربری ثبت نشده است

                                </div>

                            </td>

                        </tr>


                    @endforelse


                </tbody>


            </table>


        </div>


        <div class="mt-3">
            {{ $groupusers->links() }}
        </div>


    </div>

</div>


@endsection



@push('scripts')

<script>

document.addEventListener('DOMContentLoaded', function(){

    document.querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach(function(element){

            new bootstrap.Tooltip(element);

        });

});

</script>

@endpush