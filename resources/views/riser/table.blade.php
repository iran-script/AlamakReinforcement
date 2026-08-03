@extends('layouts.app')

@section('title', 'جدول علمک‌ها')

@section('content')

    <link href="{{ url('css/datatable.css') }}" rel="stylesheet">


    <style>
        .table-wrapper {
            position: relative;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            background: var(--surface);
            padding: 16px;
        }

        .toolbar {
            background: rgba(255, 255, 255, .96);
            border: 1px solid var(--border);
            padding: 10px 14px;
            border-radius: 12px;
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 16px;
            color: var(--text-muted);
            font-size: 13px;
        }

        .toolbar-label {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-weight: 700;
        }

        .toolbar select,
        .toolbar input {
            padding: 7px 10px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text);
            font-size: 13px;
        }

        .toolbar select:focus,
        .toolbar input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 .15rem var(--primary-soft);
        }

        .toolbar .legend {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-inline-start: auto;
            font-size: 12px;
        }

        .toolbar .legend span {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .toolbar .legend i.dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot-done {
            background: var(--success);
        }

        .dot-pending {
            background: #f1c40f;
        }

        .dot-success {
            background: var(--success);
        }

        .dot-error {
            background: var(--danger);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.pending {
            background: rgba(241, 196, 15, .12);
            color: #b8860b;
        }

        .status-badge.default {
            background: rgba(92, 97, 97, .12);
            color: #5c6161;
        }

        .status-badge.success {
            background: rgba(46, 204, 113, .12);
            color: #1e8449;
        }

        .status-badge.error {
            background: rgba(231, 76, 60, .12);
            color: #c0392b;
        }

        /* وضعیت ناظرها */
        .status-badge.approved {
            background: rgba(46, 204, 113, .12);
            color: #1e8449;
        }

        .status-badge.rejected {
            background: rgba(231, 76, 60, .12);
            color: #c0392b;
        }

        .status-badge.need_revision {
            background: rgba(52, 152, 219, .12);
            color: #21618c;
        }

        #risersTable td .status-badge {
            margin: 2px 3px 2px 0;
            display: inline-block;
        }

        #risersTable td,
        #risersTable th {
            vertical-align: middle;
            text-align: center;
            font-size: 13.5px;
        }
    </style>

    <div class="page-eyebrow">GIS / لیست علمک‌ها</div>
    <div class="page-heading">
        <h4><i class="bi bi-table text-primary"></i> جدول علمک‌ها</h4>
    </div>

    <div class="table-wrapper">

        <!-- TOOLBAR -->
        <div class="toolbar">
            <span class="toolbar-label"><i class="bi bi-sliders"></i> وضعیت:</span>
            <select id="statusFilter">
                <option value="">همه</option>
                <option value="pending">تعمیر شده</option>
                <option value="default">تعمیر نشده</option>
                <option value="success">تایید شده</option>
                <option value="error">رد شده</option>
            </select>

            <a href="{{ route('map') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-geo-alt"></i> نمایش روی نقشه
            </a>

            <div class="legend">
                <span><i class="dot dot-pending"></i> تعمیر شده</span>
                <span><i class="dot dot-done"></i> تعمیر نشده</span>
                <span><i class="dot dot-success"></i> تایید شده</span>
                <span><i class="dot dot-error"></i> رد شده</span>
            </div>
        </div>

        <!-- TABLE -->
        <table id="risersTable" class="table table-bordered table-hover w-100">
            <thead>
                <tr>
                    <th>کد</th>
                    <th>وضعیت</th>
                    <th>ناظر مقیم</th>
                    <th>تعداد عملیات</th>
                    <th>آخرین عملیات</th>
                    <th>عملیات</th>
                </tr>
            </thead>
        </table>

    </div>

    <script src="{{ asset('js/jquery.js') }}"></script>
    <script src="{{ asset('js/datatable.min.js') }}"></script>
    <script src="{{ asset('js/jquerydatatable.js') }}"></script>


    <script>
        const statusLabels = {
            pending: 'تعمیری',
            default: 'تعمیر نشده',
            success: 'تایید شده',
            error: 'رد شده'
        };

        const riserDataUrl = @json(route('riser.data'));
        const riserShowRoute = @json(route('riser.show', ['id' => '__ID__']));

        let table;

        $(function() {

            table = $('#risersTable').DataTable({
                ajax: {
                    url: riserDataUrl,
                    dataSrc: ''
                },
                columns: [{
                        data: 'code',
                        title: 'کد'
                    },
                    {
                        data: 'status',
                        title: 'وضعیت',
                        render: function(status) {
                            const label = statusLabels[status] || status || '-';
                            return `<span class="status-badge ${status}">${label}</span>`;
                        }
                    },
                    {
                        data: 'technician_name',
                        title: 'ناظر مقیم',
                        render: function(name) {
                            return name || '<span class="text-muted">-</span>';
                        }
                    },

                    
                    {
                        data: 'operation_count',
                        title: 'تعداد عملیات',
                        render: function(count) {
                            return count || 0;
                        }
                    },
                    {
                        data: 'last_operation_date',
                        title: 'آخرین عملیات',
                        render: function(date) {
                            if (!date) {
                                return '<span class="text-muted">-</span>';
                            }

                            return new Date(date).toLocaleDateString('fa-IR');
                        }
                    },
                    {
                        data: 'id',
                        title: 'عملیات',
                        orderable: false,
                        searchable: false,
                        className: 'text-center',
                        render: function(id) {
                            const url = riserShowRoute.replace('__ID__', id);
                            return `<a href="${url}" class="btn btn-primary btn-sm">
                    <i class="bi bi-eye"></i> مشاهده
                </a>`;
                        }
                    }

                ],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/fa.json'
                },
                order: [ [5, 'desc'] ],
                pageLength: 25
            });

            // فیلتر بر اساس ستون وضعیت (ستون دوم -> index 1)
            $('#statusFilter').on('change', function() {
                const value = this.value;
                table.column(1).search(value ? '^' + value + '$' : '', true, false).draw();
            });
        });
    </script>

@endsection
