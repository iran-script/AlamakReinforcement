<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
<meta charset="UTF-8">
<title>گزارش عملیات</title>

<style>
    /*
        نکته مهم درباره فونت:
        DejaVu Sans گلیف فارسی/عربی ندارد. یک فونت فارسی (Vazirmatn) را طبق
        راهنمای انتهای فایل در public/fonts قرار بده تا متن درست رندر شود.
    */
    @page {
        margin: 78px 26px 70px 26px;
    }

    @font-face {
        font-family: 'Vazirmatn';
        src: url('{{ public_path('fonts/Vazirmatn-Regular.ttf') }}') format('truetype');
        font-weight: normal;
    }

    @font-face {
        font-family: 'Vazirmatn';
        src: url('{{ public_path('fonts/Vazirmatn-Bold.ttf') }}') format('truetype');
        font-weight: bold;
    }

    * { box-sizing: border-box; }

    body {
        font-family: 'Vazirmatn', 'DejaVu Sans', sans-serif;
        direction: rtl;
        font-size: 11px;
        color: #1F2430;
        line-height: 1.7;
    }

    /* ===================== نوار نازک تکرارشونده ===================== */
    .running-header {
        position: fixed;
        top: -55px; left: 0; right: 0;
        height: 34px;
        border-bottom: 1px solid #E6E9F0;
        font-size: 9.5px;
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
        position: fixed;
        bottom: -55px; left: 0; right: 0;
        height: 34px;
        border-top: 1px solid #E6E9F0;
        padding-top: 8px;
        text-align: center;
        color: #98A1B3;
        font-size: 9.5px;
    }
    .running-footer .page-num:after {
        content: "صفحه " counter(page) " از " counter(pages);
    }

    /* ===================== کاور ===================== */
    .cover {
        position: relative;
        margin-bottom: 22px;
        padding: 22px 24px;
        background: #0F2A43;
        border-radius: 12px;
        overflow: hidden;
        text-align: center;
    }

    .cover .deco-circle {
        position: absolute;
        top: -60px; left: -60px;
        width: 180px; height: 180px;
        border-radius: 90px;
        background: rgba(232,150,60,0.14);
    }

    .cover .deco-circle-2 {
        position: absolute;
        bottom: -70px; left: 90px;
        width: 130px; height: 130px;
        border-radius: 65px;
        background: rgba(255,255,255,0.05);
    }

    .cover-inner { position: relative; }

    .cover .eyebrow {
        font-size: 25px;
        font-weight: bold;
        color: #E8963C;
        letter-spacing: .5px;
    }

    .cover .company-line {
        font-size: 13px;
        color: #CFE0EE;
        margin-top: 3px;
    }

    .cover h1 {
        font-size: 23px;
        color: #ffffff;
        margin: 12px 0 6px;
    }

    .cover .generated {
        font-size: 9.5px;
        color: #8FA5BC;
    }

    /* ===================== کارت‌های آماری ===================== */
    .stats-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 18px;
    }
    .stats-table td {
        width: 25%;
        padding: 10px 8px;
        text-align: center;
        background: rgba(255,255,255,0.05);
        border-radius: 8px;
    }
    .stats-table td + td { }
    .stats-cell-gap { width: 8px; }

    .stats-table .stat-number {
        font-size: 19px;
        font-weight: bold;
        color: #ffffff;
    }
    .stats-table .stat-number.c-done { color: #4FD8B8; }
    .stats-table .stat-number.c-progress { color: #F3B667; }

    .stats-table .stat-label {
        font-size: 9.5px;
        color: #A9BCCF;
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
        font-size: 12.5px;
        font-weight: bold;
        color: #0F2A43;
    }
    .section-head .sub {
        font-size: 9.5px;
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
        font-size: 10.5px;
        font-weight: bold;
        color: #0F2A43;
        margin-bottom: 10px;
    }

    /* دونات */
    .donut-col { width: 44%; }
    .donut-wrap { position: relative; text-align: center; }
    .donut-center-value {
        position: absolute;
        top: 38px; left: 0; right: 0;
        text-align: center;
        font-size: 20px;
        font-weight: bold;
        color: #0F2A43;
    }
    .donut-center-label {
        position: absolute;
        top: 62px; left: 0; right: 0;
        text-align: center;
        font-size: 8.5px;
        color: #98A1B3;
    }
    .legend-row {
        margin-top: 10px;
        font-size: 9.5px;
    }
    .legend-row td { padding: 3px 4px; text-align: right; }
    .legend-dot {
        display: inline-block;
        width: 7px; height: 7px;
        border-radius: 4px;
        margin-left: 5px;
        vertical-align: middle;
    }

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
        font-size: 8px;
        color: #5B6B7C;
        margin-bottom: 2px;
    }
    .trend-label {
        font-size: 8.5px;
        color: #98A1B3;
        padding-top: 5px;
        border-top: 1px solid #E6E9F0;
    }

    /* ===================== جدول اصلی ===================== */
    table.main-table { width: 100%; border-collapse: collapse; }
    table.main-table thead th {
        background: #0F2A43; color: #ffffff;
        padding: 8px 6px; font-size: 10.5px; font-weight: bold;
        border: 1px solid #0F2A43;
    }
    table.main-table tbody td {
        border: 1px solid #E6E9F0;
        padding: 6px 6px; text-align: center; font-size: 10.5px;
    }
    table.main-table tbody tr.row-even td { background: #F8F9FC; }
    table.main-table tbody tr.empty-row td {
        padding: 18px; color: #98A1B3; font-style: italic;
    }

    tr.material-line td {
        border: 1px solid #E6E9F0; border-top: none;
        padding: 5px 12px; text-align: right;
        font-size: 9.5px; color: #5B6B7C; background: #FBFCFD;
    }
    tr.material-line .material-label { color: #B5730A; font-weight: bold; }

    /* ===================== لیدربورد میله‌ای مصالح ===================== */
    .leaderboard { width: 100%; border-collapse: collapse; }
    .leaderboard td {
        padding: 7px 4px;
        font-size: 10px;
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
    .leaderboard .lb-bar {
        height: 14px;
        background: #E8963C;
        border-radius: 4px;
    }
    .leaderboard .lb-value {
        width: 90px;
        text-align: left;
        color: #5B6B7C;
        font-size: 9.5px;
    }

    /* ===================== جدول خلاصه (رایزر) ===================== */
    table.summary-table { width: 100%; border-collapse: collapse; }
    table.summary-table thead th {
        background: #ffffff; color: #0F2A43;
        border-bottom: 2px solid #0F2A43;
        padding: 6px 5px; font-size: 10px; font-weight: bold; text-align: center;
    }
    table.summary-table tbody td {
        border-bottom: 1px solid #E6E9F0;
        padding: 6px 5px; text-align: center; font-size: 10px;
    }
    table.summary-table tbody tr.empty-row td {
        padding: 14px; color: #98A1B3; font-style: italic; border-bottom: none;
    }
</style>
</head>

<body>

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

            <h1>گزارش  سیستم </h1>

            <div class="generated">
                تاریخ تولید گزارش: {{ now()->format('Y/m/d') }} — ساعت {{ now()->format('H:i') }}
            </div>

            <table class="stats-table">
                <tr>
                    <td>
                        <div class="stat-number">{{ $data['cards']['total'] ?? 0 }}</div>
                        <div class="stat-label">کل عملیات</div>
                    </td>
                    <td class="stats-cell-gap"></td>
                    <td>
                        <div class="stat-number c-done">{{ $data['cards']['done'] ?? 0 }}</div>
                        <div class="stat-label">انجام شده</div>
                    </td>
                    <td class="stats-cell-gap"></td>
                    <td>
                        <div class="stat-number c-progress">{{ $data['cards']['progress'] ?? 0 }}</div>
                        <div class="stat-label">در حال انجام</div>
                    </td>
                    <td class="stats-cell-gap"></td>
                    <td>
                        <div class="stat-number">{{ number_format($data['cards']['total_cost'] ?? 0) }}</div>
                        <div class="stat-label">هزینه کل (ریال)</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>


    {{-- ===================== داشبورد: دونات + روند ===================== --}}
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
        $topMaterials = collect($data['materials'] ?? [])
            ->sortByDesc('qty')
            ->take(5)
            ->values();

        $maxMaterialQty = max($topMaterials->max('qty') ?? 0, 1);

        // ===== مصرف مصالح به تفکیک علمک (رایزر) =====
        $riserMaterialUsage = collect($data['table'] ?? [])
            ->groupBy(fn($row) => $row['riser'] ?? 'نامشخص')
            ->map(function ($rows) {
                return collect($rows)
                    ->flatMap(fn($row) => $row['materials'] ?? [])
                    ->sum(fn($m) => (float) ($m['qty'] ?? 0));
            })
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

                    <table class="legend-row" style="width:100%;">
                        @foreach ($segments as $seg)
                            @if ($seg['value'] > 0)
                                <tr>
                                    <td>
                                        <span class="legend-dot" style="background:{{ $seg['color'] }};"></span>
                                        {{ $seg['label'] }}
                                    </td>
                                    <td style="text-align:left; width:40px;">{{ $seg['value'] }}</td>
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
                                <td style="color:#98A1B3; font-style:italic; font-size:10px;">داده‌ای موجود نیست</td>
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
        <thead>
            <tr>
                <th width="4%">#</th>
                <th>رایزر</th>
                <th>نوع عملیات</th>
                <th>قرارداد</th>
                <th>تاریخ</th>
                <th>وضعیت</th>
                <th>اولویت</th>
                <th>کاربر</th>
                <th>هزینه</th>
            </tr>
        </thead>

        <tbody>
            @forelse($data['table'] ?? [] as $index => $row)
                <tr class="{{ $index % 2 == 1 ? 'row-even' : '' }}">
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $row['riser'] ?? '-' }}</td>
                    <td>{{ $row['operation'] ?? '-' }}</td>
                    <td>{{ $row['contract_id'] ?? '-' }}</td>
                    <td>{{ $row['date'] ?? '-' }}</td>
                    <td>{{ $row['status'] ?? '-' }}</td>
                    <td>{{ $row['priority'] ?? '-' }}</td>
                    <td>{{ $row['user'] ?? '-' }}</td>
                    <td>{{ number_format($row['cost'] ?? 0) }}</td>
                </tr>

                @if (!empty($row['materials']))
                    <tr class="material-line">
                        <td colspan="9">
                            <span class="material-label">مصالح: </span>
                            {{ collect($row['materials'])->map(fn($m) => "{$m['title']} ({$m['qty']} {$m['unit']})")->implode('،  ') }}
                        </td>
                    </tr>
                @endif
            @empty
                <tr class="empty-row">
                    <td colspan="9">هیچ عملیاتی برای این بازه یافت نشد</td>
                </tr>
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
                    <div class="lb-bar" style="width: {{ max(4, ($material['qty'] / $maxMaterialQty) * 100) }}%;"></div>
                </td>
                <td class="lb-value">{{ $material['qty'] }} {{ $material['unit'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" style="color:#98A1B3; font-style:italic; text-align:center; padding:14px;">
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

</body>
</html>