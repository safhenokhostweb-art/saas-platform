<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>ورود / ثبت نام</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <style>
        @include('partials.vazirmatn-font-faces-inline')
        body {
            font-family: Vazirmatn, Tahoma, sans-serif;
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .auth-card {
            background: #fff;
            padding: 2.5rem;
            border-radius: 20px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
        }
    </style>

    {!! NoCaptcha::renderJs() !!}
</head>
<body>
    @yield('content')
</body>
</html>
