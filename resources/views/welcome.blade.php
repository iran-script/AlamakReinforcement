<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'سامانه علمک‌ها') }}</title>

    <link href="{{ url('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ url('css/bootstrap-icon.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css" rel="stylesheet">

    <style>
        :root {
            --bg: #F4F6FA;
            --surface: #FFFFFF;
            --border: #E6E9F0;
            --text: #1F2430;
            --muted: #667085;
            --primary: #3E63DD;
            --primary-soft: #EAEEFD;
        }
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .splash {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 20px 60px -30px rgba(16,24,40,.25);
            max-width: 460px;
            width: 100%;
            padding: 44px 36px;
            text-align: center;
        }
        .mark {
            width: 60px; height: 60px;
            border-radius: 14px;
            background: var(--primary-soft);
            color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 28px;
            margin: 0 auto 20px;
        }
        h1 { font-size: 19px; font-weight: 800; margin-bottom: 8px; }
        p { color: var(--muted); font-size: 13.5px; line-height: 1.9; margin-bottom: 26px; }
        .btn-primary {
            background: var(--primary); border: none; border-radius: 9px;
            padding: 11px 28px; font-weight: 700;
        }
        .btn-primary:hover { background: #3453BE; }
    </style>
</head>
<body>

    <div class="splash">
        <div class="mark"><i class="bi bi-diagram-3"></i></div>
        <h1>سامانه مدیریت و بازرسی علمک‌های گاز</h1>
        <p>ثبت عملیات تعمیر، مستندسازی مصالح مصرفی، تایید ناظر و ردیابی موقعیت علمک‌ها روی نقشه GIS.</p>

        @auth
            <a href="{{ url('/dashboard') }}" class="btn btn-primary">
                ورود به داشبورد
            </a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary">
                ورود به سامانه
            </a>
        @endauth
    </div>

</body>
</html>
