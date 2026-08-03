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


                <div class="col-md-3" style="display: none">
                    <label>از تاریخ</label>
                    <input  id="fromDate" type="date" class="form-control">
                </div>


                <div class="col-md-3" style="display: none">
                    <label>تا تاریخ</label>
                    <input id="toDate" type="date" class="form-control">
                </div>



                <div class="col-md-3">

                    <label>وضعیت</label>

                    <select id="status" class="form-select">

                        <option value="">همه</option>

                        <option value="انجام شده">
                            انجام شده
                        </option>

                        <option value="درحال انجام">
                            درحال انجام
                        </option>

                        <option value="اتمام">
                            اتمام
                        </option>

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


            </div>

        </div>

    </div>




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


        </div>


    </div>




@endsection




@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>



    <script>
        let monthChart;
        let statusChart;



        function loadReport() {


            $.ajax({

                url: "{{ route('report.data') }}",

                type: "GET",

                data: {


                    from: $("#fromDate").val(),

                    to: $("#toDate").val(),

                    status: $("#status").val(),

                    operation_category: $("#operation_category").val(),

                    contract: $("#contract").val(),
                    zone:$("#zone").val()


                },


                success: function(res) {



                    // cards


                    $("#totalOperation")
                        .text(res.cards.total);


                    $("#doneOperation")
                        .text(res.cards.done);


                    $("#progressOperation")
                        .text(res.cards.progress);


                    $("#totalCost")
                        .text(
                            Number(res.cards.total_cost)
                            .toLocaleString()
                        );





                    // table


                    let html = "";


                    res.table.forEach(function(row, index) {


                        html += `

<tr>

<td>${index+1}</td>

<td>${row.riser ?? '-'}</td>

<td>${row.operation ?? '-'}</td>

<td>${row.date ?? '-'}</td>

<td>${row.status ?? '-'}</td>

<td>${row.priority ?? '-'}</td>

<td>${row.user ?? '-'}</td>

<td>${Number(row.cost ?? 0).toLocaleString()}</td>


</tr>

`;


                    });


                    $("#reportTable tbody")
                        .html(html);





                    drawCharts(res);



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

                loadReport();

            });



        $("#btnExcel")
            .click(function() {


                window.location.href =
                    "{{ route('report.excel') }}?from=" +
                    $("#fromDate").val() +
                    "&to=" +
                    $("#toDate").val() +
                    "&status=" +
                    $("#status").val() +
                    "&operation_category=" +
                    $("#operation_category").val() +
                    "&contract=" +
                    $("#contract").val();


            });




        $("#btnPdf")
            .click(function() {


                window.location.href =
                    "{{ route('report.pdf') }}?from=" +
                    $("#fromDate").val() +
                    "&to=" +
                    $("#toDate").val() +
                    "&status=" +
                    $("#status").val() +
                    "&operation_category=" +
                    $("#operation_category").val() +
                    "&contract=" +
                    $("#contract").val();


            });




        $(document).ready(function() {

            loadReport();

        });
    </script>
@endpush
