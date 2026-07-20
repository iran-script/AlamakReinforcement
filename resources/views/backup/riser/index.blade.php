@extends('layouts.app')

@section('title', 'لیست علمک ها')

@section('content')

<div class="container-fluid">

    <div class="card shadow-sm border-0">

        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">لیست علمک ها</h5>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-striped table-hover table-bordered align-middle">

                    <thead class="table-dark">
                        <tr>
                            <th width="80">ID</th>
                            <th>کد علمک</th>
                            <th>وضعیت</th>
                            <th width="150">عملیات</th>
                        </tr>
                    </thead>

                    <tbody>

                    @forelse($risers as $riser)

                        <tr>
                            <td>{{ $riser->id }}</td>
                            <td>{{ $riser->r_giscode}}</td>
                            <td>{{ $riser->status}}</td>

                            <td>
                                <a href="#"
                                   class="btn btn-sm btn-primary">
                                    مشاهده
                                </a>

                                <a href="#"
                                   class="btn btn-sm btn-warning">
                                    تغییرات
                                </a>
                            </td>
                        </tr>

                    @empty

                        <tr>
                            <td colspan="3" class="text-center">
                                هیچ رکوردی یافت نشد
                            </td>
                        </tr>

                    @endforelse

                    </tbody>

                </table>

            </div>

            <div class="mt-3">
                {{ $risers->links() }}
            </div>

        </div>

    </div>

</div>

@endsection