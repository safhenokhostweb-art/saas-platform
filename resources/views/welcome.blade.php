@php
    // The platform's public front door (panel root). Persian, RTL and
    // self-contained: locally served Vazirmatn and inline styles only, so it
    // needs no third-party origin (the stock Laravel page loaded Figtree
    // from fonts.bunny.net, which the CSP blocks) and no Vite build.
    $brand = config('app.name');
    $brand = ($brand === null || $brand === '' || $brand === 'Laravel') ? 'صفحه نخست' : $brand;
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>{{ $brand }} — پلتفرم ساخت و مدیریت وب‌سایت</title>
    <style>
        @include('theme-runtime::css.fonts')
        :root {
            --bg: #f6f7fb; --surface: #ffffff; --text: #111827; --muted: #4b5563; --border: #e5e7eb;
            --primary: #4f46e5; --primary-hover: #4338ca; --on-primary: #ffffff; --ring: #a5b4fc;
        }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0b1020; --surface: #121a2f; --text: #f3f4f6; --muted: #c7cbd6; --border: #253049; --primary: #818cf8; --primary-hover: #a5b4fc; --on-primary: #0b1020; --ring: #6366f1; }
        }
        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body { margin: 0; min-block-size: 100vh; background: var(--bg); color: var(--text); font-family: 'Vazirmatn', Tahoma, sans-serif; line-height: 1.8; overflow-wrap: anywhere; }
        a { color: inherit; }
        .wrap { inline-size: min(72rem, 100%); margin-inline: auto; padding-inline: 1.25rem; }
        header { border-block-end: 1px solid var(--border); background: var(--surface); }
        .bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; min-block-size: 4rem; padding-block: .5rem; }
        .brand { font-weight: 800; font-size: 1.2rem; text-decoration: none; }
        nav { display: flex; flex-wrap: wrap; gap: .5rem; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-block-size: 2.75rem; min-inline-size: 2.75rem; padding-inline: 1.15rem; border-radius: .75rem; font-weight: 700; font-size: .95rem; text-decoration: none; border: 1px solid transparent; transition: background-color .15s, border-color .15s; }
        .btn-primary { background: var(--primary); color: var(--on-primary); }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-ghost { border-color: var(--border); background: transparent; }
        .btn-ghost:hover { border-color: var(--primary); }
        .btn:focus-visible { outline: 3px solid var(--ring); outline-offset: 2px; }
        main { padding-block: clamp(2.5rem, 8vw, 5.5rem); }
        h1 { margin: 0 0 1rem; font-size: clamp(1.75rem, 5vw, 2.75rem); line-height: 1.35; font-weight: 800; }
        .lead { margin: 0 0 1.75rem; max-inline-size: 40rem; color: var(--muted); font-size: clamp(1rem, 2.4vw, 1.15rem); }
        .actions { display: flex; flex-wrap: wrap; gap: .75rem; }
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(15rem, 100%), 1fr)); gap: 1rem; margin-block-start: clamp(2.5rem, 7vw, 4rem); padding: 0; list-style: none; }
        .card { padding: 1.25rem; border: 1px solid var(--border); border-radius: 1rem; background: var(--surface); }
        .card h2 { margin: 0 0 .35rem; font-size: 1.05rem; font-weight: 700; }
        .card p { margin: 0; color: var(--muted); font-size: .95rem; }
        footer { padding-block: 1.5rem; color: var(--muted); font-size: .875rem; border-block-start: 1px solid var(--border); }
        @media (prefers-reduced-motion: reduce) { .btn { transition: none; } }
    </style>
</head>
<body>
    <header>
        <div class="wrap bar">
            <a class="brand" href="{{ url('/') }}">{{ $brand }}</a>
            @if (Route::has('login'))
                <nav aria-label="حساب کاربری">
                    @auth
                        <a class="btn btn-primary" href="{{ url(\App\Providers\RouteServiceProvider::HOME) }}">ورود به پیشخوان</a>
                    @else
                        <a class="btn btn-ghost" href="{{ route('login') }}">ورود</a>
                        @if (Route::has('register'))
                            <a class="btn btn-primary" href="{{ route('register') }}">ساخت حساب</a>
                        @endif
                    @endauth
                </nav>
            @endif
        </div>
    </header>

    <main class="wrap">
        <h1>وب‌سایت و فروشگاه حرفه‌ای خود را بسازید و مدیریت کنید</h1>
        <p class="lead">{{ $brand }} پلتفرم فارسی ساخت وب‌سایت است: قالب‌ساز بصری، فروشگاه، آموزش، ارتباط با مشتری و پشتیبانی، همه در یک پنل.</p>
        <div class="actions">
            @guest
                @if (Route::has('register'))
                    <a class="btn btn-primary" href="{{ route('register') }}">شروع کنید</a>
                @endif
                @if (Route::has('login'))
                    <a class="btn btn-ghost" href="{{ route('login') }}">قبلاً حساب دارم</a>
                @endif
            @else
                <a class="btn btn-primary" href="{{ url(\App\Providers\RouteServiceProvider::HOME) }}">رفتن به پیشخوان</a>
            @endguest
        </div>

        <ul class="features">
            <li class="card">
                <h2>قالب‌ساز بصری</h2>
                <p>صفحه‌ها، هدر و فوتر را با ویجت‌های آماده و نمایش زنده برای موبایل و دسکتاپ بچینید.</p>
            </li>
            <li class="card">
                <h2>فروشگاه و محتوا</h2>
                <p>محصولات، سبد خرید، وبلاگ و دوره‌های آموزشی را از همین پنل مدیریت کنید.</p>
            </li>
            <li class="card">
                <h2>ارتباط با مشتری</h2>
                <p>مدیریت مشتریان، تیکت پشتیبانی و پیام‌رسان‌های ایرانی در کنار سایت شما.</p>
            </li>
        </ul>
    </main>

    <footer>
        @php
            $today = now('Asia/Tehran');
            [$jalaliYear] = \SaaSPlatform\ThemeRuntime\Support\JalaliDate::fromGregorian((int) $today->year, (int) $today->month, (int) $today->day);
        @endphp
        <div class="wrap">© {{ \SaaSPlatform\ThemeRuntime\Support\PersianNumber::digits($jalaliYear) }} {{ $brand }}</div>
    </footer>
</body>
</html>
