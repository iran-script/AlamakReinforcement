@extends('layouts.app')

@section('title', 'گزارشات')

@section('content')
    <script src="{{ asset('js/jquery.js') }}"></script>
    <div class="page-heading">

        <div>
            <h4>
                <i class="bi bi-bar-chart-line-fill text-primary"></i>
                گزارشات سیستم
            </h4>
            <small class="text-muted">
                گزارش عملیات، رایزرها و وضعیت پروژه
            </small>
        </div>


        <div class="d-flex gap-2">

            <button class="btn btn-outline-secondary" id="btnPrint" type="button">چاپ</button>
            <button class="btn btn-outline-primary" id="btnSaveReport" type="button">ذخیره تنظیمات</button>

            <button class="btn btn-success" id="btnExcel">
                Excel
            </button>

            <button class="btn btn-danger" id="btnPdf">
                PDF
            </button>

        </div>

    </div>



    <div class="card-box mb-4 p-2">

        <div class="card-header">
            فیلتر گزارش
        </div>


        <div class="card-body">

            <div class="row g-3">


                <div class="col-md-3">
                    <label>از تاریخ</label>
                    <input  id="fromDate" type="date" class="form-control">
                </div>


                <div class="col-md-3">
                    <label>تا تاریخ</label>
                    <input id="toDate" type="date" class="form-control">
                </div>



                <div class="col-md-3"><label for="dataset">نوع گزارش</label><select id="dataset" class="form-select"><option value="operations">گزارش عملیات</option><option value="materials">مصرف مصالح (مقدار/واحد)</option><option value="maintenance_cases">پرونده‌های تعمیر</option></select></div>
                <div class="col-md-3">

                    <label>وضعیت</label>

                    <select id="status" class="form-select">
    <option value="">همه وضعیت‌ها</option>
    @foreach($statuses as $statusOption)<option value="{{ $statusOption }}">{{ $statusOption }}</option>@endforeach
</select>

                </div>

                <div class="col-md-3">

                    <label>
                        نوع عملیات
                    </label>

                    <select id="operation_category" class="form-select">

                        <option value="">
                            همه
                        </option>


                        @foreach ($operations as $operation)
                            <option value="{{ $operation->id }}">
                                {{ $operation->title }}
                            </option>
                        @endforeach


                    </select>

                </div>



                <div class="col-md-3">

                    <label>
                        پیمان
                    </label>


                    <select id="contract" class="form-select">

                        <option value="">
                            همه
                        </option>


                        @foreach ($contracts as $contract)
                            <option value="{{ $contract->id }}">
                                {{ $contract->contractor_name }}
                                -
                                {{ $contract->contractor_number }}
                            </option>
                        @endforeach


                    </select>


                </div>

                <div class="col-md-3">

                    <label>زون</label>

                    <select id="zone" class="form-select">

                        <option value="">همه زون‌ها</option>

                        @foreach ($zones as $zone)
                            <option value="{{ $zone->id }}">
                                {{ $zone->name }}
                            </option>
                        @endforeach

                    </select>

                </div>


                <div class="col-md-3 mb-3 d-flex align-items-end">

                    <button id="btnSearch" class="btn btn-primary w-100">

                        جستجو

                    </button>

                </div>

                <div class="col-md-3"><label>گروه تعمیراتی</label><select id="group_user_id" class="form-select"><option value="">همه گروه‌های مجاز</option>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label>ناظر ثبت‌کننده</label><select id="supervisor" class="form-select"><option value="">همه</option>@foreach($fieldSupervisors as $reportSupervisor)<option value="{{ $reportSupervisor->id }}">{{ $reportSupervisor->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label>وضعیت پرونده تعمیر</label><select id="case_status" class="form-select"><option value="">همه</option><option value="open">باز</option><option value="in_progress">در حال اجرا</option><option value="submitted">ارسال‌شده</option><option value="returned">برگشتی</option><option value="completed">تکمیل‌شده</option></select></div>
                <div class="col-md-3"><label>مصالح</label><select id="material" class="form-select"><option value="">همه</option>@foreach($materials as $material)<option value="{{ $material->id }}">{{ $material->title }} ({{ $material->unit }})</option>@endforeach</select></div>
                <div class="col-md-3"><label>اولویت</label><input id="priority" class="form-control" maxlength="100"></div>
                <div class="col-md-3"><label>ستون‌های خروجی</label><select id="reportColumns" class="form-select" multiple size="4">
                    @foreach(['riser'=>'کد علمک','operation'=>'نوع عملیات','date'=>'تاریخ','status'=>'وضعیت','priority'=>'اولویت','user'=>'ناظر ثبت‌کننده','group'=>'گروه تعمیراتی','case_status'=>'وضعیت پرونده تعمیر','cost'=>'total_cost ذخیره‌شده','materials'=>'مصالح: مقدار و واحد'] as $key=>$label)<option data-report-dataset="operations" value="{{ $key }}" {{ in_array($key, ['riser','operation','date','status','priority','user','cost','materials'], true) ? 'selected' : '' }}>{{ $label }}</option>@endforeach
@foreach(['material_title'=>'مصالح','unit'=>'واحد','qty'=>'مقدار مصرف'] as $key=>$label)<option data-report-dataset="materials" value="{{ $key }}">{{ $label }}</option>@endforeach
@foreach(['riser'=>'GISCode','organization'=>'سازمان','zone'=>'Zone جغرافیایی','supervisor'=>'ناظر ثبت‌کننده','group'=>'گروه اجرایی','identified_at'=>'تاریخ شناسایی','description'=>'شرح خرابی','priority'=>'اولویت','status'=>'وضعیت پرونده','operation_date'=>'تاریخ تعمیر','operation'=>'نوع تعمیر','submitted_at'=>'زمان ارسال گزارش','last_editor'=>'آخرین ویرایش‌کننده','photos_count'=>'تعداد تصاویر','repair_count'=>'دفعات تعمیر علمک'] as $key=>$label)<option data-report-dataset="maintenance_cases" value="{{ $key }}">{{ $label }}</option>@endforeach
                </select></div>
                <div class="col-md-3 d-flex gap-2 align-items-end"><button type="button" class="btn btn-outline-secondary" id="columnUp">بالا بردن ستون</button><button type="button" class="btn btn-outline-secondary" id="columnDown">پایین بردن ستون</button></div>
                <div class="col-md-3"><label>مرتب‌سازی</label><select id="sort" class="form-select"><option value="date">تاریخ</option><option value="status">وضعیت</option><option value="priority">اولویت</option></select></div>
                <div class="col-md-3"><label>جهت</label><select id="direction" class="form-select"><option value="desc">نزولی</option><option value="asc">صعودی</option></select></div>
                <div class="col-md-3"><label>گروه‌بندی</label><select id="group_by" class="form-select"><option value="">بدون گروه‌بندی</option><option value="status">وضعیت</option><option value="operation">نوع عملیات</option><option value="priority">اولویت</option><option value="user">ناظر ثبت‌کننده</option><option value="group">گروه تعمیراتی</option><option value="case_status">وضعیت پرونده</option><option value="zone">Zone</option></select></div>


            </div>

        </div>

    </div>




    <div class="card-box mb-3 p-3"><strong>گزارش‌های ذخیره‌شده شما</strong><div class="d-flex flex-wrap gap-2 mt-2">
        @forelse($savedReports as $savedReport)
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('reports.saved.open', $savedReport->id) }}">{{ $savedReport->name }}</a>
        @empty
            <span class="text-muted">تنظیم ذخیره‌شده‌ای ندارید.</span>
        @endforelse
    </div></div>

    <div id="reportGroups" class="card-box mb-3 p-3" hidden></div>

    <div class="row g-3">


        <div class="col-md-3">
            <div class="stat-card">

                <div id="totalOperation" class="stat-value">
                    0
                </div>

                <div>
                    کل عملیات
                </div>

            </div>
        </div>



        <div class="col-md-3">
            <div class="stat-card">

                <div id="doneOperation" class="stat-value">
                    0
                </div>

                <div>
                    انجام شده
                </div>

            </div>
        </div>



        <div class="col-md-3">
            <div class="stat-card">

                <div id="progressOperation" class="stat-value">
                    0
                </div>

                <div>
                    در حال انجام
                </div>

            </div>
        </div>



        <div class="col-md-3">
            <div class="stat-card">

                <div id="totalCost" class="stat-value">
                    0
                </div>

                <div>
                    مجموع هزینه
                </div>

            </div>
        </div>


    </div>




    <div class="row mt-4">


        <div class="col-md-6">

            <div class="card-box">

                <div class="card-header">
                    عملیات ماهانه
                </div>

                <div class="card-body">

                    <canvas id="monthChart"></canvas>

                </div>

            </div>

        </div>




        <div class="col-md-6">

            <div class="card-box">

                <div class="card-header">
                    وضعیت عملیات
                </div>

                <div class="card-body">

                    <canvas id="statusChart"></canvas>

                </div>

            </div>

        </div>


    </div>





    <div class="card-box mt-4">


        <div class="card-header">
            لیست عملیات
        </div>


        <div class="card-body">

            <div id="reportLoadError" class="alert alert-danger" role="alert" hidden>دریافت گزارش ناموفق بود؛ فیلترها را بررسی و دوباره تلاش کنید.</div>

            <table class="table table-bordered" id="reportTable">


                <thead>

                    <tr>

                        <th>#</th>
                        <th>رایزر</th>
                        <th>عملیات</th>
                        <th>تاریخ</th>
                        <th>وضعیت</th>
                        <th>اولویت</th>
                        <th>کاربر</th>
                        <th>هزینه</th>


                    </tr>

                </thead>


                <tbody></tbody>


            </table>

            <div class="d-flex justify-content-between align-items-center mt-3" dir="rtl">
                <span id="reportPaginationInfo" class="text-muted"></span>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary" id="reportPrevious">قبلی</button>
                    <button type="button" class="btn btn-outline-primary" id="reportNext">بعدی</button>
                </div>
            </div>


        </div>


    </div>




@endsection




@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>



    <script>
        let monthChart;
        let statusChart;
        let reportPage = Number(new URLSearchParams(window.location.search).get('page') || 1);

        function reportFilters() {
            return {
                from: $("#fromDate").val(), to: $("#toDate").val(), status: $("#status").val(),
                operation_category: $("#operation_category").val(), contract: $("#contract").val(),
                zone: $("#zone").val(), group_user_id: $('#group_user_id').val(), supervisor: $('#supervisor').val(), case_status: $('#case_status').val(),
                material: $('#material').val(), priority: $('#priority').val(), columns: $('#reportColumns').val(),
                sort: $('#sort').val(), direction: $('#direction').val(), group_by: $('#group_by').val(),
                dataset: $('#dataset').val(), page: reportPage
            };
        }

        function reportQueryString() {
            const params = new URLSearchParams();
            Object.entries(reportFilters()).forEach(([key, value]) => {
                if (Array.isArray(value)) value.forEach(item => params.append(`${key}[]`, item));
                else if (value !== null && value !== '') params.append(key, value);
            });
            return params.toString();
        }

        function escapeReportValue(value) {
            return $('<div>').text(value ?? '-').html();
        }

        function updateDatasetColumns(reset = false) {
            const type = $('#dataset').val() || 'operations';
            const select = document.getElementById('reportColumns');
            Array.from(select.options).forEach(option => {
                option.hidden = option.dataset.reportDataset !== type;
                if (reset) option.selected = option.dataset.reportDataset === type;
            });
            $('#status').closest('.col-md-3').toggle(type === 'operations');
            $('#case_status').closest('.col-md-3').toggle(type !== 'materials');
            $('#material').closest('.col-md-3').toggle(type !== 'maintenance_cases');
            $('#operation_category').closest('.col-md-3').toggle(type === 'operations');
            $('#contract').closest('.col-md-3').toggle(type === 'operations');
        }



        function loadReport() {


            $.ajax({

                url: "{{ route('report.data') }}",

                type: "GET",

                data: reportFilters(),


                success: function(res) {
                    $('#reportLoadError').prop('hidden', true);



                    // cards


                    $("#totalOperation")
                        .text(res.cards.total);


                    $("#doneOperation")
                        .text(res.cards.done);


                    $("#progressOperation")
                        .text(res.cards.progress);

                    $('#reportPaginationInfo').text(`صفحه ${res.pagination.current_page} از ${res.pagination.last_page} — ${res.pagination.total} رکورد`);
                    $('#reportPrevious').prop('disabled', res.pagination.current_page <= 1);
                    $('#reportNext').prop('disabled', res.pagination.current_page >= res.pagination.last_page);
                    const query = reportQueryString();
                    history.replaceState(null, '', `${window.location.pathname}?${query}`);


                    $("#totalCost")
                        .text(
                            Number(res.cards.total_cost)
                            .toLocaleString()
                        );





                    // table


                    $('#reportTable thead').html('<tr><th>#</th>' + res.columns.map(c => `<th>${escapeReportValue(c.label)}</th>`).join('') + '</tr>');
                    let html = '';
                    if (!res.table.length) html = `<tr><td colspan="${res.columns.length + 1}" class="text-center text-muted">داده‌ای برای فیلترهای انتخاب‌شده وجود ندارد.</td></tr>`;
                    res.table.forEach(function(row, index) {
                        const cells = res.columns.map(column => {
                            let value = row[column.key];
                            if (column.key === 'materials') value = (value || []).map(m => `${m.title} (${m.qty} ${m.unit})`).join('، ');
                            if (column.key === 'cost') value = Number(value || 0).toLocaleString();
                            return `<td>${escapeReportValue(value)}</td>`;
                        }).join('');
                        html += `<tr><td>${(res.pagination.current_page - 1) * res.pagination.per_page + index + 1}</td>${cells}</tr>`;
                    });


                    $("#reportTable tbody")
                        .html(html);





                    drawCharts(res);

                    const groups = $('#reportGroups');
                    if (res.grouping && res.grouping.length) {
                        groups.prop('hidden', false).html('<strong>خلاصه گروه‌بندی</strong><div class="d-flex flex-wrap gap-2 mt-2">' + res.grouping.map(g => `<span class="badge bg-secondary">${escapeReportValue(g.label)}: ${Number(g.count).toLocaleString()}</span>`).join('') + '</div>');
                    } else groups.prop('hidden', true).empty();



                },
                error: function() {
                    $('#reportLoadError').prop('hidden', false);
                }



            });


        }





        function drawCharts(res) {



            if (monthChart)
                monthChart.destroy();



            monthChart = new Chart(

                document.getElementById('monthChart'),

                {

                    type: 'bar',

                    data: {

                        labels: res.monthChart.labels,


                        datasets: [{

                            label: 'تعداد عملیات',

                            data: res.monthChart.data

                        }]

                    }

                }

            );






            if (statusChart)
                statusChart.destroy();



            statusChart = new Chart(

                document.getElementById('statusChart'),

                {

                    type: 'pie',

                    data: {

                        labels: res.statusChart.labels,


                        datasets: [{

                            data: res.statusChart.data

                        }]


                    }

                }

            );



        }





        $("#btnSearch")
            .click(function() {
                reportPage = 1;
                loadReport();

            });



        $("#btnExcel")
            .click(function() {
                window.location.href = "{{ route('report.excel') }}?" + reportQueryString();
            });




        $("#btnPdf")
            .click(function() {
                window.location.href = "{{ route('report.pdf') }}?" + reportQueryString();
            });

        $('#btnPrint').on('click', function() { window.print(); });

        function moveSelectedColumn(direction) {
            const select = document.getElementById('reportColumns');
            const options = Array.from(select.options);
            if (direction < 0) {
                for (let i = 1; i < options.length; i++) if (options[i].selected && !options[i - 1].selected) [options[i - 1], options[i]] = [options[i], options[i - 1]];
            } else {
                for (let i = options.length - 2; i >= 0; i--) if (options[i].selected && !options[i + 1].selected) [options[i], options[i + 1]] = [options[i + 1], options[i]];
            }
            select.replaceChildren(...options);
        }
        $('#columnUp').on('click', () => moveSelectedColumn(-1));
        $('#columnDown').on('click', () => moveSelectedColumn(1));

        $('#btnSaveReport').on('click', function() {
            const name = window.prompt('نام این تنظیم گزارش را وارد کنید:');
            if (!name) return;
            const configuration = reportFilters();
            delete configuration.page;
            $.ajax({url: "{{ route('reports.saved.store') }}", method: 'POST', contentType: 'application/json',
                headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                data: JSON.stringify({name, configuration}),
                success: function() { window.location.reload(); },
                error: function(xhr) { alert(xhr.status === 403 ? 'محدوده انتخابی مجاز نیست.' : 'ذخیره گزارش انجام نشد؛ فیلترها را بررسی کنید.'); }
            });
        });




        $(document).ready(function() {

            const params = new URLSearchParams(window.location.search);
            [ ['fromDate','from'], ['toDate','to'], ['status','status'], ['operation_category','operation_category'], ['contract','contract'], ['zone','zone'], ['group_user_id','group_user_id'], ['supervisor','supervisor'], ['case_status','case_status'], ['material','material'], ['priority','priority'], ['sort','sort'], ['direction','direction'], ['group_by','group_by'], ['dataset','dataset'] ]
                .forEach(([element, key]) => { if (params.has(key)) $('#' + element).val(params.get(key)); });
            if (params.getAll('columns[]').length) $('#reportColumns').val(params.getAll('columns[]'));
            updateDatasetColumns(false);
            if (!params.getAll('columns[]').length) updateDatasetColumns(true);
            $('#dataset').on('change', function() { updateDatasetColumns(true); reportPage = 1; });
            $('#reportPrevious').on('click', function() { reportPage = Math.max(1, reportPage - 1); loadReport(); });
            $('#reportNext').on('click', function() { reportPage += 1; loadReport(); });

            loadReport();

        });
    </script>
@endpush
