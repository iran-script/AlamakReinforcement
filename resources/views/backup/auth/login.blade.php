<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ورود - سیستم مقاوم‌سازی علمک‌ها</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="{{ url('css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ url('css/bootstrap-icon.css') }}" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg:      #0B1220;
            --panel:   #121B2E;
            --panel-2: #16233A;
            --border:  #223252;
            --text:    #E7ECF5;
            --muted:   #8B9AB8;
            --amber:   #F0A93B;
            --teal:    #33C6B0;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            color: var(--text);
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background:
                radial-gradient(1100px 600px at 15% -10%, rgba(76, 141, 255, .12), transparent 55%),
                radial-gradient(900px 600px at 100% 110%, rgba(240, 169, 59, .10), transparent 55%),
                var(--bg);
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(#223252 1px, transparent 1px),
                linear-gradient(90deg, #223252 1px, transparent 1px);
            background-size: 42px 42px;
            opacity: .14;
        }

        .rig {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 920px;
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            background: linear-gradient(180deg, var(--panel-2), var(--panel));
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 30px 80px -30px rgba(0,0,0,.7);
        }

        .rig-side {
            padding: 44px 38px;
            background:
                repeating-linear-gradient(135deg, rgba(255,255,255,.02) 0 2px, transparent 2px 22px);
            border-inline-end: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .rig-mark {
            width: 56px; height: 56px;
            border-radius: 12px;
            background: linear-gradient(155deg, #26344F, #131C2C);
            border: 1px solid var(--border);
            display: flex; align-items: center; justify-content: center;
            color: var(--amber);
            font-size: 26px;
            margin-bottom: 22px;
        }

        .rig-side h1 {
            font-size: 22px;
            font-weight: 800;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .rig-side p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.9;
        }

        .rig-status {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 26px;
        }

        .rig-status .dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--teal);
            box-shadow: 0 0 8px var(--teal);
        }

        .rig-form {
            padding: 44px 38px;
        }

        .title {
            font-weight: 800;
            font-size: 20px;
            margin-bottom: 6px;
        }

        .subtitle {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 26px;
        }

        .form-label {
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 6px;
            display: block;
        }

        .form-control {
            background: var(--panel);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 9px;
            padding: 11px 14px;
        }

        .form-control:focus {
            background: var(--panel);
            border-color: var(--amber);
            color: var(--text);
            box-shadow: 0 0 0 .2rem rgba(240,169,59,.15);
            outline: none;
        }

        .form-control::placeholder { color: #4A5876; }

        .btn-primary {
            background: var(--amber);
            border: none;
            color: #1A1204;
            font-weight: 700;
            border-radius: 9px;
            padding: 12px;
            width: 100%;
            transition: .2s;
        }

        .btn-primary:hover { background: #ffbb52; }

        .alert-danger {
            background: rgba(229,72,77,.1);
            border: 1px solid rgba(229,72,77,.4);
            color: #ff9a9d;
            border-radius: 9px;
            font-size: 13px;
            padding: 10px 14px;
            margin-bottom: 18px;
        }

        @media (max-width: 760px) {
            .rig { grid-template-columns: 1fr; }
            .rig-side { border-inline-end: none; border-bottom: 1px solid var(--border); }
        }
    </style>
</head>

<body>

<div class="rig">

    <div class="rig-side">
        <div>
            <div class="rig-mark"><i class="bi bi-diagram-3"></i></div>
            <h1>سامانه مدیریت و بازرسی علمک‌های گاز</h1>
            <p>ثبت عملیات تعمیر، مستندسازی مصالح مصرفی، تایید ناظر بر اساس تصاویر قبل و بعد، و ردیابی موقعیت هر علمک روی نقشه GIS.</p>
        </div>
        <div class="rig-status">
            <span class="dot"></span>
            اتصال امن برقرار است
        </div>
    </div>

    <div class="rig-form">

        <div class="title">ورود به سیستم</div>
        <div class="subtitle">برای دسترسی به کنسول عملیات، وارد شوید</div>

        @if ($errors->any())
            <div class="alert-danger">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.post') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">نام کاربری</label>
                <input type="text"
                       name="username"
                       class="form-control"
                       placeholder="مثلاً saeed xxxxxxx"
                       required>
            </div>

            <div class="mb-4">
                <label class="form-label">رمز عبور</label>
                <input type="password"
                       name="password"
                       class="form-control"
                       placeholder="رمز عبور"
                       required>
            </div>

            <button type="submit" class="btn btn-primary">
                ورود
            </button>
        </form>

    </div>

</div>

</body>
</html>
