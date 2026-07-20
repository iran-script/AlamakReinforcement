@extends('layouts.app')

@section('title', 'لیست علمک ها')

@section('content')

<div class="page-eyebrow">REGISTRY / RISERS</div>
<div class="page-heading">
    <h4><i class="bi bi-collection text-primary"></i> لیست علمک‌ها</h4>
</div>

<div class="card-box">

    <div class="card-body p-3">

        <div class="table-responsive">

            <table class="table table-striped table-hover table-bordered align-middle mb-0">

                <thead>
                    <tr>
                        <th width="80">ID</th>
                        <th>کد علمک</th>
                        <th>وضعیت</th>
                        <th width="170">عملیات</th>
                    </tr>
                </thead>

                <tbody>

                @forelse($risers as $riser)

                    <tr>
                        <td class="text-muted" style="font-family: var(--font-mono); font-size: 12.5px;">#{{ $riser->id }}</td>
                        <td>
                            <span class="plate">{{ $riser->r_giscode }}</span>
                        </td>
                        <td>
                            @php
                                $doneStatuses   = ['تعمیر شده', 'انجام شده'];
                                $rejectStatuses = ['رد شده'];
                                $pillClass = in_array($riser->status, $doneStatuses)
                                    ? 'status-done'
                                    : (in_array($riser->status, $rejectStatuses) ? 'status-reject' : 'status-pending');
                            @endphp
                            <span class="status-pill {{ $pillClass }}">{{ $riser->status }}</span>
                        </td>

                        <td>
                            <a href="#" class="btn btn-sm btn-primary">
                                <i class="bi bi-eye"></i>
                                مشاهده
                            </a>

                            <a href="#" class="btn btn-sm btn-secondary">
                                <i class="bi bi-clock-history"></i>
                                تغییرات
                            </a>
                        </td>
                    </tr>

                @empty

                    <tr>
                        <td colspan="4">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                هیچ رکوردی یافت نشد
                            </div>
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

@endsection
