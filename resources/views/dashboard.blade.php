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

    <form class="card-box p-3 mb-3" id="dashboardFilters" dir="rtl">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-lg-2"><label for="dashFrom" class="form-label">از تاریخ</label><input id="dashFrom" name="from" type="date" class="form-control"></div>
            <div class="col-6 col-lg-2"><label for="dashTo" class="form-label">تا تاریخ</label><input id="dashTo" name="to" type="date" class="form-control"></div>
            <div class="col-6 col-lg-2"><label for="dashZone" class="form-label">Zone</label><select id="dashZone" name="zone" class="form-select"><option value="">همه محدوده مجاز</option>@foreach($dashboardZones as $zone)<option value="{{ $zone->id }}">{{ $zone->name }}</option>@endforeach</select></div>
            <div class="col-6 col-lg-2"><label for="dashType" class="form-label">نوع عملیات</label><select id="dashType" name="operation_category" class="form-select"><option value="">همه</option>@foreach($dashboardCategories as $category)<option value="{{ $category->id }}">{{ $category->title }}</option>@endforeach</select></div>
            <div class="col-6 col-lg-2"><label for="dashStatus" class="form-label">وضعیت (مقدار ثبت‌شده)</label><select id="dashStatus" name="status" class="form-select"><option value="">همه</option>@foreach($dashboardStatuses as $status)<option value="{{ $status }}">{{ $status }}</option>@endforeach</select></div>
            <div class="col-6 col-lg-2"><label for="dashExecutor" class="form-label">گروه تعمیراتی</label><select id="dashExecutor" name="group_user_id" class="form-select"><option value="">همه گروه‌های مجاز</option>@foreach($dashboardGroups as $dashboardGroup)<option value="{{ $dashboardGroup->id }}">{{ $dashboardGroup->name }}</option>@endforeach</select></div>
            <div class="col-6 col-lg-2"><label for="dashSupervisor" class="form-label">ناظر ثبت‌کننده</label><select id="dashSupervisor" name="supervisor" class="form-select"><option value="">همه</option>@foreach($dashboardSupervisors as $supervisor)<option value="{{ $supervisor->id }}">{{ $supervisor->name }}</option>@endforeach</select></div>
            <div class="col-6 col-lg-2"><label for="compare1From" class="form-label">مقایسه ۱ از</label><input id="compare1From" name="compare1_from" type="date" class="form-control"></div>
            <div class="col-6 col-lg-2"><label for="compare1To" class="form-label">مقایسه ۱ تا</label><input id="compare1To" name="compare1_to" type="date" class="form-control"></div>
            <div class="col-6 col-lg-2"><label for="compare2From" class="form-label">مقایسه ۲ از</label><input id="compare2From" name="compare2_from" type="date" class="form-control"></div>
            <div class="col-6 col-lg-2"><label for="compare2To" class="form-label">مقایسه ۲ تا</label><input id="compare2To" name="compare2_to" type="date" class="form-control"></div>
            <div class="col-12 col-lg-2"><button class="btn btn-primary w-100" type="submit">اعمال فیلتر</button></div>
        </div>
        <small class="text-muted d-block mt-2">کارت کل علمک‌ها مستقل از تاریخ و نوع عملیات است؛ Zone انتخابی آن را محدود می‌کند.</small>
    </form>

    <div class="row g-3 mt-1" id="dashboardKpis" dir="rtl">
        @foreach ([['kpiRisers','کل علمک‌های مجاز'],['kpiRepairedRisers','علمک‌های دارای عملیات تأییدشده'],['kpiOperations','کل عملیات فیلترشده'],['kpiToday','عملیات ثبت‌شده امروز'],['kpiPending','عملیات در انتظار تأیید ناظر'],['kpiApproved','عملیات تأییدشده'],['kpiReturned','عملیات برگشتی'],['kpiRepeated','علمک‌های پرتکرار در فیلتر'],['kpiExecutors','مجریان فعال'],['kpiSupervisors','ناظران فعال']] as [$id,$label])
            <div class="col-6 col-md-4 col-xl-2"><div class="stat-card h-100"><div id="{{ $id }}" class="stat-value">—</div><div class="stat-label">{{ $label }}</div></div></div>
        @endforeach
        <div class="col-12"><small class="text-muted">فیلترهای تاریخ و نوع عملیات روی شاخص‌ها اعمال می‌شوند؛ «کل علمک‌های مجاز» فقط به Zone انتخابی محدود می‌شود.</small></div>
    </div>

    @if($maintenanceApprovalStats)
        <div class="card-box mt-3 p-3 d-flex flex-wrap justify-content-between align-items-center gap-3" dir="rtl">
            <div><h5 class="mb-1"><i class="bi bi-check2-square text-primary"></i> تأیید تعمیرات</h5><small class="text-muted">صف بررسی پرونده‌های ارسال‌شده توسط گروه‌های تعمیراتی</small></div>
            <div class="d-flex flex-wrap gap-3 align-items-center"><span class="small">در انتظار: <strong>{{ number_format($maintenanceApprovalStats['submitted']) }}</strong></span><span class="small">تأییدشده: <strong>{{ number_format($maintenanceApprovalStats['completed']) }}</strong></span><span class="small">برگشت‌خورده: <strong>{{ number_format($maintenanceApprovalStats['returned']) }}</strong></span><a class="btn btn-primary" href="{{ route('boss.maintenance-cases.index') }}">ورود به صف تأیید</a></div>
        </div>
    @endif

    @if($maintenanceReturnedCount !== null)
        <div class="card-box mt-3 p-3 d-flex flex-wrap justify-content-between align-items-center gap-3 border-warning" dir="rtl">
            <div>
                <h5 class="mb-1"><i class="bi bi-arrow-counterclockwise text-warning"></i> پرونده‌های برگشتی برای اصلاح</h5>
                <small class="text-muted">پرونده‌هایی که برای اصلاح به شما یا گروه مسئول شما بازگردانده شده‌اند.</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="display-6 fw-bold text-warning">{{ number_format($maintenanceReturnedCount) }}</span>
                @if($maintenanceReturnedCount > 0)
                    <a class="btn btn-warning" href="{{ route('maintenance-cases.index', ['status' => 'returned']) }}">مشاهده پرونده‌های برگشتی</a>
                @else
                    <span class="small text-muted">پرونده برگشتی ندارید</span>
                @endif
            </div>
        </div>
    @endif

    <div class="row g-3 mt-1" id="dashboardCharts" dir="rtl">
        @foreach ([['dashboardMonthlyChart','روند ماهانه عملیات'],['dashboardStatusChart','توزیع وضعیت ثبت‌شده'],['dashboardRiserStatusesChart','وضعیت علمک‌ها (مقدار خام)'],['dashboardTypesChart','عملیات به تفکیک نوع تعمیر'],['dashboardZonesChart','عملیات به تفکیک Zone'],['dashboardPendingZonesChart','عملیات در انتظار تأیید به تفکیک Zone'],['dashboardExecutorsChart','عملکرد مجریان'],['dashboardSupervisorsChart','عملکرد ناظران'],['dashboardMaterialsChart','مصرف مصالح (مقدار و واحد)'],['dashboardMaterialsZoneChart','مصرف مصالح در هر Zone (مقدار و واحد)'],['dashboardRisersChart','علمک‌های پرتکرار'],['dashboardApprovalChart','روند تأیید و برگشت'],['dashboardCompareChart','مقایسه دو بازه زمانی']] as [$canvas,$title])
            <div class="col-12 col-xl-6"><div class="card-box p-3 h-100"><h5>{{ $title }}</h5><div class="dashboard-loading text-muted">در حال بارگذاری…</div><div class="dashboard-empty text-muted" hidden>برای این فیلتر داده‌ای وجود ندارد.</div><div class="position-relative" style="height:280px"><canvas id="{{ $canvas }}" aria-label="{{ $title }}" hidden></canvas></div></div></div>
        @endforeach
        <div id="dashboardChartError" class="col-12 text-danger" role="alert" hidden>بارگذاری نمودارها ناموفق بود.</div>
    </div>


    {{-- آمار اصلی --}}

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

                                        {{ number_format($material->total_qty) }} {{ $material->unit }}

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

@push('scripts')
    <script src="{{ asset('js/chart.umd.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('dashboardFilters');
            const charts = {};
            const specs = [
                ['dashboardMonthlyChart','monthly','bar','تعداد عملیات'], ['dashboardStatusChart','status','doughnut','تعداد عملیات'],
                ['dashboardRiserStatusesChart','riser_statuses','doughnut','تعداد علمک'],
                ['dashboardTypesChart','operation_types','bar','تعداد عملیات'], ['dashboardZonesChart','zones','bar','تعداد عملیات'],
                ['dashboardPendingZonesChart','pending_by_zone','bar','عملیات در انتظار تأیید'],
                ['dashboardExecutorsChart','executors','bar','تعداد عملیات'], ['dashboardSupervisorsChart','supervisors','bar','تعداد عملیات'],
                ['dashboardMaterialsChart','materials','bar','مقدار مصرف (با واحد در برچسب)'],
                ['dashboardMaterialsZoneChart','materials_by_zone','bar','مقدار مصرف (واحد در برچسب)'], ['dashboardRisersChart','top_risers','bar','دفعات تعمیر'],
                ['dashboardCompareChart','comparisons','bar','تعداد عملیات']
            ];
            const chartOptions = {responsive:true, maintainAspectRatio:false, plugins:{legend:{display:true, position:'bottom'}, tooltip:{enabled:true}}};
            function paint(id, labels, datasets, type='bar') {
                const canvas = document.getElementById(id), card = canvas.closest('.card-box');
                card.querySelector('.dashboard-loading').hidden = true;
                if (!labels || labels.length === 0) { canvas.hidden = true; card.querySelector('.dashboard-empty').hidden = false; return; }
                canvas.hidden = false;
                if (charts[id]) charts[id].destroy();
                charts[id] = new Chart(canvas, {type, data:{labels, datasets:datasets.map((d,i)=>({backgroundColor:['#2563eb','#16a34a','#f59e0b','#dc2626','#7c3aed'][i%5], borderColor:['#2563eb','#16a34a','#f59e0b','#dc2626','#7c3aed'][i%5], ...d}))}, options:chartOptions});
            }
            async function load() {
                document.getElementById('dashboardChartError').hidden = true;
                document.querySelectorAll('.dashboard-loading').forEach(el => el.hidden = false);
                document.querySelectorAll('.dashboard-empty').forEach(el => el.hidden = true);
                const params = new URLSearchParams(new FormData(form));
                history.replaceState(null, '', `${location.pathname}?${params.toString()}`);
                try {
                    const response = await fetch(`{{ route('dashboard.statistics') }}?${params}`, {headers:{Accept:'application/json'}});
                    if (!response.ok) throw new Error('Request failed');
                    const data = await response.json();
                    document.getElementById('kpiRisers').textContent = Number(data.total_risers).toLocaleString();
                    document.getElementById('kpiRepairedRisers').textContent = Number(data.approved_risers).toLocaleString();
                    document.getElementById('kpiOperations').textContent = Number(data.total).toLocaleString();
                    document.getElementById('kpiToday').textContent = Number(data.today_operations).toLocaleString();
                    document.getElementById('kpiPending').textContent = Number(data.pending_approval).toLocaleString();
                    document.getElementById('kpiApproved').textContent = Number(data.approved_operations).toLocaleString();
                    document.getElementById('kpiReturned').textContent = Number(data.returned_operations).toLocaleString();
                    document.getElementById('kpiRepeated').textContent = Number(data.repeated_risers).toLocaleString();
                    document.getElementById('kpiExecutors').textContent = Number(data.active_executors).toLocaleString();
                    document.getElementById('kpiSupervisors').textContent = Number(data.active_supervisors).toLocaleString();
                    for (const [id,key,type,label] of specs) paint(id, data[key].labels, [{label, data:data[key].data}], type);
                    paint('dashboardApprovalChart', data.approval_trend.labels, [
                        {label:'تأییدشده', data:data.approval_trend.approved, fill:false},
                        {label:'برگشتی', data:data.approval_trend.returned, fill:false}
                    ], 'line');
                } catch (_) {
                    document.querySelectorAll('.dashboard-loading').forEach(el => el.hidden = true);
                    document.getElementById('dashboardChartError').hidden = false;
                }
            }
            for (const [key, value] of new URLSearchParams(location.search)) {
                const input = form.elements.namedItem(key);
                if (input) input.value = value;
            }
            form.addEventListener('submit', event => { event.preventDefault(); load(); });
            load();
        });
    </script>
@endpush
