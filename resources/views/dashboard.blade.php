@extends('layouts.app')

@section('title', 'داشبورد')

@section('content')

    <div class="page-eyebrow">OVERVIEW</div>

    <div class="page-heading">
        <h4>
            <i class="bi bi-speedometer2 text-primary"></i>
            داشبورد سیستم مقاوم‌سازی علمک‌ها
        </h4>
    </div>


    {{-- آمار اصلی --}}

    <div class="row g-3">


        <div class="col-6 col-md-3">
            <div class="stat-card">

                <div class="stat-value">
                    {{ number_format($totalRisers) }}
                </div>

                <div class="stat-label">
                    <i class="bi bi-collection"></i>
                    کل علمک‌ها
                </div>

            </div>
        </div>



        <div class="col-6 col-md-3">
            <div class="stat-card">

                <div class="stat-value" style="color:var(--success)">
                    {{ number_format($doneRisers) }}
                </div>

                <div class="stat-label">
                    <i class="bi bi-check-circle"></i>
                    انجام شده
                </div>

            </div>
        </div>



        <div class="col-6 col-md-3">
            <div class="stat-card">

                <div class="stat-value" style="color:var(--warning)">
                    {{ number_format($pendingRisers) }}
                </div>

                <div class="stat-label">
                    <i class="bi bi-hourglass-split"></i>
                    باقی مانده
                </div>

            </div>
        </div>



        <div class="col-6 col-md-3">
            <div class="stat-card">

                <div class="stat-value" style="color:var(--primary)">
                    {{ number_format($todayOperations) }}
                </div>

                <div class="stat-label">
                    <i class="bi bi-lightning-charge"></i>
                    عملیات امروز
                </div>

            </div>
        </div>


    </div>




    {{-- کاربران و پیشرفت --}}

    <div class="row g-3 mt-1">


        <div class="col-md-4">

            <div class="stat-card">

                <div class="stat-value" style="color:var(--primary)">
                    {{ number_format($activeUsers) }}
                </div>

                <div class="stat-label">
                    <i class="bi bi-people"></i>
                    کاربران فعال
                </div>

            </div>

        </div>

        <div class="col-md-4">

            <div class="stat-card">

                <div class="stat-value" style="color:var(--warning)">
                    {{ number_format($totalMaterialQty) }}
                </div>

                <div class="stat-label">
                    <i class="bi bi-box-seam"></i>
                    کل اقلام مصرفی
                </div>

            </div>

        </div>



        <div class="col-md-4">

            <div class="stat-card">

                <div class="stat-value">
                    {{ number_format($totalZones) }}
                </div>

                <div class="stat-label">
                    <i class="bi bi-map"></i>
                    تعداد زون‌ها
                </div>

            </div>

        </div>



        {{-- <div class="col-md-4">

            <div class="stat-card">

                <div class="stat-value" style="color:var(--success)">
                    {{ $progress }}%
                </div>

                <div class="stat-label">
                    <i class="bi bi-graph-up"></i>
                    پیشرفت پروژه
                </div>

            </div>

        </div> --}}


    </div>






    {{-- آخرین عملیات --}}

    <div class="card-box mt-3">


        <div class="p-3">

            <h5>
                <i class="bi bi-clock-history text-primary"></i>
                آخرین عملیات‌ها
            </h5>


            <div class="table-responsive">

                <table class="table table-bordered">


                    <thead>

                        <tr>

                            <th>علمک</th>

                            <th>نوع عملیات</th>

                            <th>ثبت کننده</th>

                            <th>تاریخ</th>

                            {{-- <th>وضعیت</th> --}}

                        </tr>

                    </thead>


                    <tbody>


                        @forelse($latestOperations as $operation)
                            <tr>


                                <td>

                                    @if ($operation->riser)
                                        <span class="plate">
                                            {{ $operation->riser->giscode ?? $operation->riser->id }}
                                        </span>
                                    @else
                                        -
                                    @endif

                                </td>



                                <td>

                                    {{ $operation->operationCategory?->title ?? '-' }}

                                </td>



                                <td>

                                    {{ $operation->user?->name ?? '-' }}

                                </td>



                                <td>

                                    {{ $operation->created_at?->format('Y-m-d') }}

                                </td>



                                {{-- <td>
                                    
                                    @if ($operation->status == 'default')
                                        <span class="status-pill status-done">
                                            پیش‌فرض
                                        </span>
                                    @elseif($operation->status == 'flagRiser')
                                        <span class="status-pill status-pending">
                                            علامت‌گذاری شده
                                        </span>
                            

                                    @elseif($operation->status == 'submitOperation')
                                        <span class="status-pill status-pending">
                                         📤 ثبت عمیرات 
                                        </span>
                                  

                                     @elseif($operation->status == 'approved')
                                        <span class="status-pill status-pending">
                                         ✅ تایید شده
                                        </span>
                                    @else


                                        <span class="badge bg-primary">
                                            {{ $operation->status }}
                                        </span>
                                    @endif


                                </td>
 --}}

                            </tr>


                        @empty


                            <tr>

                                <td colspan="5" class="text-center">

                                    عملیاتی ثبت نشده است

                                </td>

                            </tr>
                        @endforelse


                    </tbody>


                </table>

            </div>


        </div>


    </div>



    {{-- عملکرد پیمان ها --}}


    <div class="card-box mt-3">


        <div class="p-3">


            <h5>

                <i class="bi bi-person-workspace text-primary"></i>

               گزارش پیمان ها

            </h5>



            <div class="table-responsive">


                <table class="table table-bordered">


                    <thead>

                        <tr>

                            <th>پیمان</th>

                            <th>تعداد عملیات ثبت شده</th>

                        </tr>

                    </thead>



                    <tbody>


                        @foreach ($contract as $c)
                            <tr>


                                <td>

                                    {{ $c->contractor_name }} ( {{ $c->contract_number }})

                                </td>


                                <td>

                                    <span class="badge bg-primary">

                                        {{ $c->operations_count }}

                                    </span>


                                </td>


                            </tr>
                        @endforeach


                    </tbody>


                </table>


            </div>


        </div>


    </div>



    {{-- عملکرد کاربران --}}


    <div class="card-box mt-3">


        <div class="p-3">


            <h5>

                <i class="bi bi-person-workspace text-primary"></i>

                عملکرد کاربران

            </h5>



            <div class="table-responsive">


                <table class="table table-bordered">


                    <thead>

                        <tr>

                            <th>کاربر</th>

                            <th>تعداد عملیات ثبت شده</th>

                        </tr>

                    </thead>



                    <tbody>


                        @foreach ($userStats as $user)
                            <tr>


                                <td>

                                    {{ $user->name }}

                                </td>


                                <td>

                                    <span class="badge bg-primary">

                                        {{ $user->operations_count }}

                                    </span>


                                </td>


                            </tr>
                        @endforeach


                    </tbody>


                </table>


            </div>


        </div>


    </div>

    <div class="card-box mt-3">

        <div class="p-3">

            <h5>
                <i class="bi bi-box-seam text-primary"></i>
                پرمصرف‌ترین اقلام
            </h5>

            <div class="table-responsive">

                <table class="table table-bordered">

                    <thead>

                        <tr>

                            <th>نام کالا</th>

                            <th width="180">مقدار مصرف</th>

                        </tr>

                    </thead>

                    <tbody>

                        @forelse($topMaterials as $material)
                            <tr>

                                <td>{{ $material->title }}</td>

                                <td>

                                    <span class="badge bg-success">

                                        {{ number_format($material->total_qty) }}

                                    </span>

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td colspan="2" class="text-center">

                                    اطلاعاتی وجود ندارد.

                                </td>

                            </tr>
                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>

    </div>


@endsection
