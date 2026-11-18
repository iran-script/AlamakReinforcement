<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
<meta charset="UTF-8">
<title>گزارش عملیات</title>

<style>
    .report-meta-header { display: none; }
    .report-meta-header .report-brand { color: #0F2A43; font-size: 19px; font-weight: bold; }
    .report-meta-header h1 { color: #264b6b; font-size: 15px; margin: 2px 0 8px; }
    .report-meta-grid { display: flex; justify-content: space-between; gap: 12px; color: #52657a; font-size: 9.5px; }
    .active-filters { margin-top: 8px; color: #52657a; font-size: 9px; line-height: 1.9; }
    .active-filters span { display: inline-block; background: #e9f0f6; border-radius: 5px; padding: 1px 6px; margin: 0 3px; }
    * { box-sizing: border-box; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        direction: rtl;
        font-size: 10pt;
        color: #1F2430;
        line-height: 1.7;
    }

    /* ===================== نوار نازک تکرارشونده ===================== */
    .running-header {
        display: none;
        height: 34px;
        border-bottom: 1px solid #E6E9F0;
        font-size: 9pt;
        color: #98A1B3;
        padding-bottom: 6px;
        text-align: center;
    }
    .running-header .r-right { float: right; }
    .running-header .r-left { float: left; }
    .running-header .r-mark {
        display: inline-block; width: 14px; height: 14px;
        background: #E8963C; border-radius: 3px;
        vertical-align: middle; margin-left: 5px;
    }

    .running-footer {
        display: none;
        height: 34px;
        border-top: 1px solid #E6E9F0;
        padding-top: 8px;
        text-align: center;
        color: #98A1B3;
        font-size: 9pt;
    }
    .running-footer .page-num:after {
        content: "صفحه " counter(page) " از " counter(pages);
    }

    /* ===================== کاور ===================== */
    .cover {
        position: relative;
        height: 154px;
        margin-bottom: 18px;
        padding: 12px 24px;
        background: #0F2A43;
        border-radius: 12px;
        overflow: hidden;
        text-align: center;
        page-break-inside: avoid;
    }

    .cover .deco-circle {
        position: absolute;
        top: -38px; left: -38px;
        width: 120px; height: 120px;
        border-radius: 60px;
        background: rgba(232,150,60,0.14);
    }

    .cover .deco-circle-2 {
        position: absolute;
        bottom: -45px; left: 72px;
        width: 90px; height: 90px;
        border-radius: 45px;
        background: rgba(255,255,255,0.05);
    }

    .cover-inner { position: relative; }

    .cover .eyebrow {
        font-size: 13pt;
        font-weight: bold;
        color: #E8963C;
        letter-spacing: .5px;
    }

    .cover .company-line {
        font-size: 9.5pt;
        color: #CFE0EE;
        margin-top: 3px;
    }

    .cover h1 {
        font-size: 21pt;
        color: #ffffff;
        margin: 5px 0 3px;
    }

    .cover .generated {
        font-size: 9pt;
        color: #8FA5BC;
    }
    .cover .report-meta-inline {
        margin-top: 4px;
        color: #cfe0ee;
        font-size: 8.5pt;
    }

    /* ===================== کارت‌های آماری ===================== */
    .stats-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 6px;
    }
    .stats-table td {
        width: 25%;
        padding: 5px 8px;
        text-align: center;
        background: #ffffff;
        border: 1px solid #d9e4ee;
    }
    .stats-table td + td { }
    .stats-cell-gap { width: 8px; }

    .stats-table .stat-number {
        font-size: 20pt;
        font-weight: bold;
        color: #0F2A43 !important;
    }
    .stats-table .stat-number.c-done { color: #137A64 !important; }
    .stats-table .stat-number.c-progress { color: #A96B18 !important; }

    .stats-table .stat-label {
        font-size: 8.5pt;
        color: #52657A !important;
        margin-top: 3px;
    }

    /* ===================== عنوان بخش ===================== */
    .section-head {
        margin-top: 24px;
        margin-bottom: 10px;
        padding-bottom: 5px;
        border-bottom: 1.5px solid #0F2A43;
    }
    .section-head .tag {
        display: inline-block;
        width: 7px; height: 7px;
        background: #E8963C;
        margin-left: 6px;
    }
    .section-head .label {
        font-size: 13pt;
        font-weight: bold;
        color: #0F2A43;
    }
    .section-head .sub {
        font-size: 9pt;
        color: #98A1B3;
        float: left;
    }

    /* ===================== داشبورد (دونات + میله ماهانه) ===================== */
    .dash-cols {
        width: 100%;
        border-collapse: separate;
        border-spacing: 14px 0;
    }
    .dash-cols > tr > td {
        vertical-align: top;
        padding: 0;
    }
    .dash-panel {
        border: 1px solid #E6E9F0;
        border-radius: 10px;
        padding: 14px;
    }
    .dash-panel .panel-title {
        font-size: 9.5pt;
        font-weight: bold;
        color: #0F2A43;
        margin-bottom: 10px;
    }

    /* دونات */
    .donut-col { width: 44%; }
    .donut-wrap { display: none; }
    .donut-center-value {
        position: absolute;
        top: 38px; left: 0; right: 0;
        text-align: center;
        font-size: 12pt;
        font-weight: bold;
        color: #0F2A43;
    }
    .donut-center-label {
        position: absolute;
        top: 62px; left: 0; right: 0;
        text-align: center;
        font-size: 8.5pt;
        color: #98A1B3;
    }
    .legend-row { display: none; }
    .legend-row td { padding: 3px 4px; text-align: right; }
    .legend-dot {
        display: inline-block;
        width: 7px; height: 7px;
        border-radius: 4px;
        margin-left: 5px;
        vertical-align: middle;
    }

    .metric-bars { width: 100%; border-collapse: collapse; margin-top: 7px; }
    .metric-bars td { padding: 4px 2px; vertical-align: middle; font-size: 8.5pt; }
    .metric-bars .metric-label { width: 115px; color: #263b50; }
    .metric-bars .metric-track { background: #edf2f7; height: 11px; }
    .metric-bars .metric-track-inner { width: 100%; border-collapse: collapse; height: 11px; }
    .metric-bars .metric-track-inner td { height: 11px; padding: 0; }
    .metric-bars .metric-fill { height: 11px; }
    .metric-bars .metric-value { width: 32px; text-align: left; color: #17324d; font-weight: bold; }

    /* میله ماهانه */
    .trend-col { width: 56%; }
    .trend-chart-table {
        width: 100%;
        border-collapse: collapse;
    }
    .trend-chart-table td {
        text-align: center;
        vertical-align: bottom;
        height: 90px;
        padding: 0 4px;
    }
    .trend-bar {
        width: 20px;
        margin: 0 auto;
        background: #0F2A43;
        border-radius: 3px 3px 0 0;
    }
    .trend-value {
        font-size: 8pt;
        color: #5B6B7C;
        margin-bottom: 2px;
    }
    .trend-label {
        font-size: 8.5pt;
        color: #98A1B3;
        padding-top: 5px;
        border-top: 1px solid #E6E9F0;
    }

    /* ===================== جدول اصلی ===================== */
    table.main-table { width: 100%; border-collapse: collapse; }
    table.main-table thead th {
        background: #0F2A43; color: #ffffff;
        padding: 8px 6px; font-size: 9.5pt; font-weight: bold;
        border: 1px solid #0F2A43;
    }
    table.main-table tbody td {
        border: 1px solid #E6E9F0;
        padding: 6px 6px; text-align: center; font-size: 9pt;
    }
    table.main-table tbody tr.row-even td { background: #F8F9FC; }
    table.main-table tbody tr.empty-row td {
        padding: 18px; color: #98A1B3; font-style: normal;
    }

    tr.material-line td {
        border: 1px solid #E6E9F0; border-top: none;
        padding: 5px 12px; text-align: right;
        font-size: 9pt; color: #5B6B7C; background: #FBFCFD;
    }
    tr.material-line .material-label { color: #B5730A; font-weight: bold; }

    /* ===================== لیدربورد میله‌ای مصالح ===================== */
    .leaderboard { width: 100%; border-collapse: collapse; }
    .leaderboard td {
        padding: 7px 4px;
        font-size: 9.5pt;
        vertical-align: middle;
    }
    .leaderboard .lb-rank {
        width: 20px;
        color: #98A1B3;
        font-weight: bold;
        text-align: center;
    }
    .leaderboard .lb-label {
        width: 140px;
        color: #1F2430;
    }
    .leaderboard .lb-bar-cell {
        background: #F1F3F7;
        border-radius: 4px;
    }
    .leaderboard .lb-track { width: 100%; border-collapse: collapse; height: 12px; }
    .leaderboard .lb-track td { height: 12px; padding: 0; }
    .leaderboard .lb-value {
        width: 90px;
        text-align: left;
        color: #5B6B7C;
        font-size: 9pt;
    }

    /* ===================== جدول خلاصه (رایزر) ===================== */
    table.summary-table { width: 100%; border-collapse: collapse; }
    table.summary-table thead th {
        background: #ffffff; color: #0F2A43;
        border-bottom: 2px solid #0F2A43;
        padding: 6px 5px; font-size: 9.5pt; font-weight: bold; text-align: center;
    }
    table.summary-table tbody td {
        border-bottom: 1px solid #E6E9F0;
        padding: 6px 5px; text-align: center; font-size: 9pt;
    }
    table.summary-table tbody tr.empty-row td {
        padding: 14px; color: #98A1B3; font-style: normal; border-bottom: none;
    }
</style>
</head>

<body>

    <section class="report-meta-header">
        <div class="report-brand">{{ $data['meta']['company'] ?? 'شرکت گاز استان مرکزی' }}</div>
        <h1>گزارش مدیریتی عملیات و تعمیرات علمک‌ها</h1>
        <div class="report-meta-grid">
            <span><b>تاریخ تولید:</b> {{ $data['meta']['generated_at'] ?? now()->format('Y/m/d H:i') }}</span>
            <span><b>تهیه‌کننده:</b> {{ $data['meta']['prepared_by'] ?? 'سامانه' }}</span>
            <span><b>بازه:</b> {{ $data['meta']['from'] ?: 'ابتدا' }} تا {{ $data['meta']['to'] ?: 'امروز' }}</span>
        </div>
        @if(!empty($data['meta']['filters']))<div class="active-filters"><b>فیلترهای فعال:</b> @foreach($data['meta']['filters'] as $label => $value)<span>{{ $label }}: {{ is_array($value) ? implode('، ', $value) : $value }}</span>@endforeach</div>@endif
    </section>

    <div class="running-header">
        <span class="r-right">
            <span class="r-mark"></span>
            شرکت گاز استان مرکزی — پیمان مقاوم‌سازی علمک‌های گاز
        </span>
        <span class="r-left">گزارش عملیات</span>
    </div>

    <div class="running-footer">
        گزارش تولید شده توسط سامانه مدیریت عملیات &nbsp;|&nbsp;
        <span class="page-num"></span>
    </div>


    {{-- ===================== کاور ===================== --}}
    <div class="cover">
        <div class="deco-circle"></div>
        <div class="deco-circle-2"></div>

        <div class="cover-inner">
            <div class="eyebrow">شرکت گاز استان مرکزی</div>
            <div class="company-line">پیمان مقاوم‌سازی علمک‌های گاز</div>

            <h1>گزارش عملکرد علمک‌ها</h1>

            <div class="generated">
                تاریخ تولید گزارش: {{ now()->format('Y/m/d') }} — ساعت {{ now()->format('H:i') }}
            </div>
            <div class="report-meta-inline">
                تهیه‌کننده: {{ $data['meta']['prepared_by'] ?? 'سامانه' }}
                @if(!empty($data['meta']['from']) || !empty($data['meta']['to']))
                    &nbsp; | &nbsp; بازه: {{ $data['meta']['from'] ?: 'ابتدا' }} تا {{ $data['meta']['to'] ?: 'امروز' }}
                @endif
                @if(!empty($data['meta']['filters']))
                    &nbsp; | &nbsp; {{ collect($data['meta']['filters'])->map(fn($value, $label) => $label.': '.(is_array($value) ? implode('، ', $value) : $value))->implode(' | ') }}
                @endif
            </div>

        </div>
    </div>

    <pagebreak />

    <table class="stats-table">
        <tr>
            <td><div class="stat-number">{{ $data['cards']['total'] ?? 0 }}</div><div class="stat-label">کل عملیات</div></td>
            <td><div class="stat-number c-done">{{ $data['cards']['done'] ?? 0 }}</div><div class="stat-label">انجام شده</div></td>
            <td><div class="stat-number c-progress">{{ $data['cards']['progress'] ?? 0 }}</div><div class="stat-label">در حال انجام</div></td>
            <td><div class="stat-number">{{ number_format($data['cards']['total_cost'] ?? 0) }}</div><div class="stat-label">هزینه کل (ریال)</div></td>
        </tr>
    </table>


    {{-- ===================== داشبورد: دونات + روند ===================== --}}
    @if(($data['dataset'] ?? 'operations') === 'maintenance_cases')
    <div class="section-head"><span class="tag"></span><span class="label">فهرست پرونده‌های تعمیر</span></div>
    <table class="main-table">
        <thead><tr><th>#</th>@foreach($data['columns'] ?? [] as $column)<th>{{ $column['label'] }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($data['table'] ?? [] as $index => $row)
                <tr class="{{ $index % 2 == 1 ? 'row-even' : '' }}"><td>{{ $index + 1 }}</td>@foreach($data['columns'] ?? [] as $column)<td>{{ $row[$column['key']] ?? '-' }}</td>@endforeach</tr>
            @empty
                <tr class="empty-row"><td colspan="{{ count($data['columns'] ?? []) + 1 }}">داده‌ای برای نمایش وجود ندارد</td></tr>
            @endforelse
        </tbody>
    </table>
    @else
    @php
        // پالت رنگ برای دسته‌های نوع عملیات (به تعداد دلخواه قابل تکرار)
        $palette = ['#2FA787', '#E8963C', '#4E7FBF', '#C9639C', '#6BC0D9', '#B5730A', '#8E6BC0', '#C9D3E0'];

        // ===== دونات: تفکیک بر اساس نوع عملیات =====
        $byOperation = collect($data['table'] ?? [])
            ->groupBy(fn($row) => $row['operation'] ?? 'نامشخص')
            ->map->count()
            ->sortDesc();

        $operationTotal = max($byOperation->sum(), 1);

        $segments = collect();
        $i = 0;
        foreach ($byOperation as $label => $value) {
            $segments->push([
                'label' => $label,
                'value' => $value,
                'color' => $palette[$i % count($palette)],
            ]);
            $i++;
        }

        $radius = 42;
        $circumference = 2 * M_PI * $radius;
        $cumulative = 0;

        // ===== نمودار میله‌ای: تفکیک بر اساس کاربر =====
        $byUser = collect($data['table'] ?? [])
            ->groupBy(fn($row) => $row['user'] ?? 'نامشخص')
            ->map->count();

        $maxByUser = max($byUser->max() ?? 0, 1);

        // لیدربورد مصالح — ۵ قلم پرمصرف‌تر (بر اساس تعداد/مقدار مصرفی)
        $topMaterials = collect($data['materialUsage'] ?? [])
            ->sortByDesc('qty')
            ->take(5)
            ->values();

        $maxMaterialQty = max($topMaterials->max('qty') ?? 0, 1);

        // ===== مصرف مصالح به تفکیک علمک (رایزر) =====
        $riserMaterialUsage = collect($data['table'] ?? [])
            ->flatMap(fn($row) => collect($row['materials'] ?? [])->map(fn($m) => [
                'key' => ($row['riser'] ?? '—').' / '.($m['unit'] ?? 'واحد نامشخص'),
                'qty' => (float) ($m['qty'] ?? 0),
            ]))
            ->groupBy('key')
            ->map(fn($items) => $items->sum('qty'))
            ->filter(fn($qty) => $qty > 0)
            ->sortDesc();
    @endphp

    <div class="section-head">
        <span class="tag"></span>
        <span class="label">نمای کلی عملکرد</span>
    </div>

    <table class="dash-cols">
        <tr>
            <td class="donut-col">
                <div class="dash-panel">
                    <div class="panel-title">تفکیک عملیات بر اساس نوع</div>

                    <div class="donut-wrap">
                        <svg width="140" height="140" viewBox="0 0 120 120">
                            <g transform="rotate(-90 60 60)">
                                <circle cx="60" cy="60" r="{{ $radius }}" fill="none"
                                        stroke="#F1F3F7" stroke-width="16" />

                                @foreach ($segments as $seg)
                                    @php
                                        $fraction = $seg['value'] / $operationTotal;
                                        $dash = $fraction * $circumference;
                                    @endphp

                                    @if ($seg['value'] > 0)
                                        <circle cx="60" cy="60" r="{{ $radius }}" fill="none"
                                                stroke="{{ $seg['color'] }}" stroke-width="16"
                                                stroke-dasharray="{{ $dash }} {{ $circumference - $dash }}"
                                                stroke-dashoffset="{{ -$cumulative }}" />
                                    @endif

                                    @php $cumulative += $dash; @endphp
                                @endforeach
                            </g>
                        </svg>

                        <div class="donut-center-value">{{ $operationTotal }}</div>
                        <div class="donut-center-label">کل عملیات</div>
                    </div>

                    <table class="metric-bars">
                        @foreach ($segments as $seg)
                            @if ($seg['value'] > 0)
                                <tr>
                                    <td class="metric-label">{{ $seg['label'] }}</td>
                                    <td class="metric-track">
                                        <table class="metric-track-inner" width="100%"><tr>
                                            <td style="width: {{ max(4, ($seg['value'] / $operationTotal) * 100) }}%; background: {{ $seg['color'] }};">&nbsp;</td>
                                            <td>&nbsp;</td>
                                        </tr></table>
                                    </td>
                                    <td class="metric-value">{{ $seg['value'] }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </table>

                </div>
            </td>

            <td class="trend-col">
                <div class="dash-panel">
                    <div class="panel-title">روند تعداد عملیات به تفکیک کاربر</div>

                    <table class="trend-chart-table">
                        <tr>
                            @forelse ($byUser as $user => $count)
                                <td>
                                    <div class="trend-value">{{ $count }}</div>
                                    <div class="trend-bar" style="height: {{ max(6, ($count / $maxByUser) * 70) }}px;"></div>
                                </td>
                            @empty
                                <td style="color:#98A1B3; font-style:normal; font-size:10px;">داده‌ای موجود نیست</td>
                            @endforelse
                        </tr>
                        <tr>
                            @foreach ($byUser as $user => $count)
                                <td class="trend-label">{{ $user }}</td>
                            @endforeach
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>


    {{-- ===================== لیست عملیات ===================== --}}
    <div class="section-head">
        <span class="tag"></span>
        <span class="label">لیست عملیات</span>
    </div>

    <table class="main-table">
        <thead><tr><th>#</th>@foreach($data['columns'] ?? [] as $column)<th>{{ $column['label'] }}</th>@endforeach</tr></thead>
        <tbody>
            @forelse($data['table'] ?? [] as $index => $row)
                <tr class="{{ $index % 2 == 1 ? 'row-even' : '' }}">
                    <td>{{ $index + 1 }}</td>
                    @foreach($data['columns'] ?? [] as $column)
                        <td>
                            @if($column['key'] === 'materials')
                                {{ collect($row['materials'] ?? [])->map(fn($m) => "{$m['title']} ({$m['qty']} {$m['unit']})")->implode('، ') }}
                            @elseif($column['key'] === 'cost')
                                {{ number_format($row['cost'] ?? 0) }}
                            @else
                                {{ $row[$column['key']] ?? '-' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr class="empty-row"><td colspan="{{ count($data['columns'] ?? []) + 1 }}">برای این فیلتر داده‌ای یافت نشد.</td></tr>
            @endforelse
        </tbody>
    </table>


    {{-- ===================== لیدربورد مصالح ===================== --}}
    <div class="section-head">
        <span class="tag"></span>
        <span class="label">پرمصرف‌ترین مصالح</span>
        <span class="sub">۵ قلم برتر</span>
    </div>

    <table class="leaderboard">
        @forelse ($topMaterials as $i => $material)
            <tr>
                <td class="lb-rank">{{ $i + 1 }}</td>
                <td class="lb-label">{{ $material['title'] }}</td>
                <td class="lb-bar-cell">
                    <table class="lb-track" width="100%"><tr>
                        <td style="width: {{ max(4, ($material['qty'] / $maxMaterialQty) * 100) }}%; background:#E8963C;">&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr></table>
                </td>
                <td class="lb-value">{{ $material['qty'] }} {{ $material['unit'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="color:#98A1B3; font-style:normal; text-align:center; padding:14px;">
                    مصرفی ثبت نشده است
                </td>
            </tr>
        @endforelse
    </table>


    {{-- ===================== مصرف مصالح به تفکیک علمک ===================== --}}
    <div class="section-head">
        <span class="tag"></span>
        <span class="label">مصرف مصالح به تفکیک علمک</span>
    </div>

    <table class="summary-table">
        <thead>
            <tr>
                <th>رایزر</th>
                <th>مقدار مصالح مصرفی</th>
            </tr>
        </thead>
        <tbody>
            @forelse($riserMaterialUsage as $riser => $qty)
                <tr>
                    <td>{{ $riser }}</td>
                    <td>{{ number_format($qty) }}</td>
                </tr>
            @empty
                <tr class="empty-row">
                    <td colspan="2">داده‌ای برای نمایش وجود ندارد</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @endif
</body>
</html>
