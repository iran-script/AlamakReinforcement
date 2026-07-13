<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ورود - سیستم مقاوم‌سازی علمک‌ها</title>

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <style>
        body {
            background: #f5f6fa;
            font-family: sans-serif;
        }

        .login-box {
            width: 380px;
            margin: 120px auto;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .title {
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
        }
    </style>
</head>

<body>

<div class="login-box">

    <div class="title">
        ورود به سیستم
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.post') }}">
        @csrf

        <div class="mb-3">
            <label class="form-label">نام کاربری </label>
            <input type="text"
                   name="username"
                   class="form-control"
                   placeholder="مثلاً saeed xxxxxxx"
                   required>
        </div>

        <div class="mb-3">
            <label class="form-label">رمز عبور</label>
            <input type="password"
                   name="password"
                   class="form-control"
                   placeholder="رمز عبور"
                   required>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            ورود
        </button>
    </form>

</div>

</body>
</html>