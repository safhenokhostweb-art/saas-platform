<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ورود') — {{ \App\Helpers\ResellerBranding::platformName() }}</title>
    <style>@include('partials.vazirmatn-font-faces-inline')</style>
    @if(file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css'])
    @endif
    @include('partials.platform-styles')
    <style id="auth-login-layout-css">
        :root {
            --auth-login-primary: var(--ds-primary, #4f46e5);
            --auth-login-primary-soft: color-mix(in srgb, var(--auth-login-primary) 12%, white);
            --auth-login-hero-end: color-mix(in srgb, var(--auth-login-primary) 45%, #0f172a);
        }
        .auth-login-body {
            margin: 0;
            min-height: 100vh;
            font-family: Vazirmatn, Tahoma, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        .auth-login-grid {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr;
        }
        @media (min-width: 900px) {
            .auth-login-grid {
                grid-template-columns: minmax(420px, 1fr) minmax(0, 1.15fr);
            }
        }
        .auth-login-panel {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.75rem 1.25rem 2.5rem;
            box-sizing: border-box;
        }
        .auth-login-card-wrap {
            width: 100%;
            max-width: 420px;
        }
        .auth-login-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.65rem;
            margin-bottom: 1.35rem;
            flex-wrap: wrap;
        }
        .auth-login-brand img {
            height: 2.25rem;
            width: auto;
            object-fit: contain;
        }
        .auth-login-brand span {
            font-weight: 800;
            font-size: 1.05rem;
            color: var(--auth-login-primary);
            letter-spacing: -0.02em;
        }
        .auth-login-card {
            background: #fff;
            border-radius: 1.25rem;
            padding: 1.75rem 1.5rem 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(15, 23, 42, 0.07), 0 20px 40px -12px rgba(15, 23, 42, 0.12);
            border: 1px solid rgba(15, 23, 42, 0.06);
            box-sizing: border-box;
        }
        .auth-login-card h1 {
            margin: 0 0 0.35rem;
            font-size: 1.35rem;
            font-weight: 800;
            text-align: center;
            color: #0f172a;
        }
        .auth-login-card .auth-login-lead {
            margin: 0 0 1.25rem;
            text-align: center;
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.6;
        }
        .auth-login-body .form-label {
            display: block;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.4rem;
        }
        .auth-login-body .form-control {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.65rem;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .auth-login-body .form-control:focus {
            outline: none;
            border-color: var(--auth-login-primary);
            box-shadow: 0 0 0 3px var(--auth-login-primary-soft);
        }
        .auth-login-body .space-y-4 > * + * { margin-top: 1rem; }
        .auth-login-remember {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #475569;
        }
        .auth-login-remember input {
            width: 1.05rem;
            height: 1.05rem;
            accent-color: var(--auth-login-primary);
        }
        .auth-login-body .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.7rem 1.25rem;
            border-radius: 0.65rem;
            font-weight: 600;
            font-size: 0.95rem;
            border: none;
            cursor: pointer;
            transition: opacity 0.15s, transform 0.1s;
        }
        .auth-login-body .btn:active { transform: scale(0.99); }
        .auth-login-body .btn-primary {
            background: var(--auth-login-primary);
            color: #fff;
            width: 100%;
        }
        .auth-login-body .btn-primary:hover { opacity: 0.94; }
        .auth-login-body .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.65rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
            line-height: 1.5;
        }
        .auth-login-body .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }
        .auth-login-body .alert-success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .auth-login-footer-text {
            margin-top: 1.25rem;
            text-align: center;
            font-size: 0.8125rem;
            color: #64748b;
        }
        .auth-login-footer-text a {
            color: var(--auth-login-primary);
            font-weight: 600;
            text-decoration: none;
        }
        .auth-login-footer-text a:hover { text-decoration: underline; }
        .auth-login-hero {
            display: none;
            position: relative;
            background: linear-gradient(145deg, var(--auth-login-primary) 0%, var(--auth-login-hero-end) 100%);
            color: #fff;
            overflow: hidden;
        }
        @media (min-width: 900px) {
            .auth-login-hero { display: flex; align-items: center; justify-content: center; }
        }
        .auth-login-hero-inner {
            position: relative;
            z-index: 1;
            padding: 3rem 2.5rem;
            max-width: 420px;
            text-align: center;
        }
        .auth-login-hero-inner svg {
            width: min(280px, 70vw);
            height: auto;
            margin: 0 auto 1.25rem;
            opacity: 0.95;
            filter: drop-shadow(0 12px 24px rgba(0,0,0,0.2));
        }
        .auth-login-hero h2 {
            margin: 0 0 0.5rem;
            font-size: 1.35rem;
            font-weight: 800;
        }
        .auth-login-hero p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9rem;
            line-height: 1.75;
        }
        .auth-login-hero-blob {
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.08);
            pointer-events: none;
        }
        .auth-login-hero-blob:nth-child(1) { width: 320px; height: 320px; top: -80px; left: -60px; }
        .auth-login-hero-blob:nth-child(2) { width: 220px; height: 220px; bottom: -40px; right: -40px; }
    </style>
    @stack('head')
</head>
<body class="auth-login-body">
    <div class="auth-login-grid">
        <div class="auth-login-panel">
            <div class="auth-login-card-wrap">
                <div class="auth-login-brand">
                    @if(\App\Helpers\ResellerBranding::logoUrl())
                        <img src="{{ \App\Helpers\ResellerBranding::logoUrl() }}" alt="">
                    @endif
                    <span>{{ \App\Helpers\ResellerBranding::platformName() }}</span>
                </div>
                @if(session('success'))
                    <div class="alert alert-success" role="status">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
                @endif
                @yield('content')
            </div>
        </div>
        <div class="auth-login-hero" aria-hidden="true">
            <span class="auth-login-hero-blob"></span>
            <span class="auth-login-hero-blob"></span>
            <div class="auth-login-hero-inner">
                <svg viewBox="0 0 200 160" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect x="24" y="40" width="152" height="100" rx="14" stroke="currentColor" stroke-width="3" fill="rgba(255,255,255,0.12)"/>
                    <path d="M48 64h64M48 88h40M48 112h96" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" opacity="0.85"/>
                    <circle cx="148" cy="52" r="22" fill="rgba(255,255,255,0.2)" stroke="currentColor" stroke-width="2"/>
                    <path d="M140 52l6 6 12-14" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <h2>ورود امن به پنل</h2>
                <p>اطلاعات شما با رمزنگاری استاندارد محافظت می‌شود. از موبایل و رمز عبور معتبر خود استفاده کنید.</p>
            </div>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
