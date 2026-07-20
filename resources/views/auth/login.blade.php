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

    <style>
        :root {
            --bg:      #F4F6FA;
            --surface: #FFFFFF;
            --surface-2: #F8F9FC;
            --border:  #E6E9F0;
            --text:    #1F2430;
            --muted:   #667085;
            --primary: #3E63DD;
            --primary-soft: #EAEEFD;
            --success: #12946F;
            --success-soft: #E3F6EF;
            --danger:  #D64545;
            --danger-soft: #FDECEC;
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
            padding: 20px;
            background: var(--bg);
        }

        .rig {
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: 1.05fr 1fr;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 20px 60px -30px rgba(16,24,40,.25);
        }

        .rig-side {
            padding: 44px 38px;
            background: var(--surface-2);
            border-inline-end: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .rig-mark {
            width: 52px; height: 52px;
            border-radius: 12px;
            background: var(--primary-soft);
            color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 24px;
            margin-bottom: 22px;
        }

        .rig-side h1 { font-size: 20px; font-weight: 800; line-height: 1.7; margin-bottom: 10px; }
        .rig-side p { color: var(--muted); font-size: 13.5px; line-height: 1.9; }

        .rig-status {
            font-size: 12.5px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 26px;
        }

        .rig-status .dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 0 3px var(--success-soft);
        }

        .rig-form { padding: 44px 38px; }

        .title { font-weight: 800; font-size: 19px; margin-bottom: 6px; }
        .subtitle { color: var(--muted); font-size: 13px; margin-bottom: 26px; }

        .form-label { color: var(--muted); font-size: 12.5px; font-weight: 700; margin-bottom: 6px; display: block; }

        .form-control {
            background: var(--surface);
            border: 1px solid var(--border);
            color: var(--text);
            border-radius: 9px;
            padding: 11px 14px;
            font-size: 14px;
        }

        .form-control:focus {
            background: var(--surface);
            border-color: var(--primary);
            color: var(--text);
            box-shadow: 0 0 0 .18rem var(--primary-soft);
            outline: none;
        }

        .form-control::placeholder { color: #A6ADBB; }

        .btn-primary {
            background: var(--primary);
            border: none;
            color: #fff;
            font-weight: 700;
            border-radius: 9px;
            padding: 12px;
            width: 100%;
            transition: .15s;
        }
        .btn-primary:hover { background: #3453BE; }

        .alert-danger {
            background: var(--danger-soft);
            border: 1px solid #F5CACA;
            color: #A33333;
            border-radius: 9px;
            font-size: 13px;
            padding: 10px 14px;
            margin-bottom: 18px;
        }

        @media (max-width: 760px) {
            .rig { grid-template-columns: 1fr; }
            .rig-side { border-inline-end: none; border-bottom: 1px solid var(--border); padding: 32px 26px; }
            .rig-form { padding: 32px 26px; }
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
        <div class="subtitle">برای دسترسی به سامانه وارد شوید</div>

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
