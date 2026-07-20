<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لایسنس منقضی شده</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ url('css/bootstrap-icon.css') }}" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css" rel="stylesheet">

    <style>
        :root {
            --bg: #F4F6FA;
            --surface: #FFFFFF;
            --border: #E6E9F0;
            --text: #1F2430;
            --muted: #667085;
            --danger: #D64545;
            --danger-soft: #FDECEC;
        }
        body {
            font-family: 'Vazirmatn', Tahoma, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 20px 60px -30px rgba(16,24,40,.25);
            max-width: 480px;
        }
        .expired-icon {
            width: 68px; height: 68px;
            border-radius: 50%;
            background: var(--danger-soft);
            color: var(--danger);
            display: flex; align-items: center; justify-content: center;
            font-size: 30px;
            margin: 0 auto 20px;
        }
        h2 { font-weight: 800; font-size: 21px; }
        .lead { color: var(--muted); font-size: 14.5px; }
        .support-line {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 10px 20px;
            font-weight: 700;
            font-size: 16px;
            margin-top: 18px;
            direction: ltr;
        }
    </style>
</head>

<body>

<div class="container vh-100 d-flex justify-content-center align-items-center">

    <div class="card p-4 p-md-5 text-center">

        <div class="expired-icon">
            <i class="bi bi-exclamation-triangle-fill"></i>
        </div>

        <h2 class="text-danger mb-3">
            لایسنس نرم‌افزار منقضی شده است
        </h2>

        <p class="lead">
            برای تمدید یا فعال‌سازی مجدد با پشتیبانی تماس بگیرید.
        </p>

        <span class="support-line">
            <i class="bi bi-telephone-fill"></i>
            09397311933
        </span>

    </div>

</div>

</body>
</html>
