<!DOCTYPE html>
<html lang="fa" dir="rtl" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'نصب پلتفرم') — نصب‌کننده پلتفرم SaaS</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=vazirmatn:300,400,500,600,700&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --c-bg:         #0f1117;
            --c-surface:    #1a1d27;
            --c-surface2:   #242736;
            --c-border:     #2e3148;
            --c-primary:    #6366f1;
            --c-primary-h:  #4f52d4;
            --c-success:    #22c55e;
            --c-warning:    #f59e0b;
            --c-danger:     #ef4444;
            --c-text:       #e2e8f0;
            --c-muted:      #94a3b8;
            --c-radius:     12px;
            --c-radius-sm:  8px;
            --shadow:       0 4px 24px rgba(0,0,0,.35);
        }

        body {
            font-family: 'Vazirmatn', sans-serif;
            background: var(--c-bg);
            color: var(--c-text);
            min-height: 100vh;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Header ─────────────────────────────────────── */
        .installer-header {
            background: var(--c-surface);
            border-bottom: 1px solid var(--c-border);
            padding: 16px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .installer-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 18px;
            color: var(--c-primary);
        }
        .installer-logo svg { width: 32px; height: 32px; }
        .installer-badge {
            background: rgba(99,102,241,.15);
            color: var(--c-primary);
            border: 1px solid rgba(99,102,241,.3);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        /* ── Layout ─────────────────────────────────────── */
        .installer-body {
            max-width: 960px;
            margin: 40px auto;
            padding: 0 24px;
        }

        /* ── Progress Bar ───────────────────────────────── */
        .progress-track {
            display: flex;
            align-items: center;
            gap: 0;
            margin-bottom: 40px;
        }
        .progress-step {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }
        .progress-step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 18px;
            left: -50%;
            width: 100%;
            height: 2px;
            background: var(--c-border);
            z-index: 0;
        }
        .progress-step.done:not(:last-child)::after,
        .progress-step.active:not(:last-child)::after {
            background: var(--c-primary);
        }
        .progress-dot {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--c-surface2);
            border: 2px solid var(--c-border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
            color: var(--c-muted);
            position: relative;
            z-index: 1;
            transition: all .2s;
        }
        .progress-step.done .progress-dot {
            background: var(--c-primary);
            border-color: var(--c-primary);
            color: #fff;
        }
        .progress-step.active .progress-dot {
            background: var(--c-surface2);
            border-color: var(--c-primary);
            color: var(--c-primary);
            box-shadow: 0 0 0 4px rgba(99,102,241,.2);
        }
        .progress-label {
            font-size: 11px;
            color: var(--c-muted);
            margin-top: 8px;
            text-align: center;
            white-space: nowrap;
        }
        .progress-step.active .progress-label { color: var(--c-primary); font-weight: 600; }
        .progress-step.done .progress-label { color: var(--c-success); }

        /* ── Card ───────────────────────────────────────── */
        .card {
            background: var(--c-surface);
            border: 1px solid var(--c-border);
            border-radius: var(--c-radius);
            padding: 32px;
            box-shadow: var(--shadow);
        }
        .card-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .card-subtitle {
            color: var(--c-muted);
            font-size: 14px;
            margin-bottom: 28px;
        }
        .card-section {
            margin-bottom: 24px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--c-border);
        }
        .card-section:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--c-muted);
            text-transform: uppercase;
            letter-spacing: .05em;
            margin-bottom: 16px;
        }

        /* ── Form ───────────────────────────────────────── */
        .form-group { margin-bottom: 20px; }
        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            background: var(--c-surface2);
            border: 1px solid var(--c-border);
            border-radius: var(--c-radius-sm);
            padding: 10px 14px;
            color: var(--c-text);
            font-family: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color .2s;
        }
        .form-control:focus { border-color: var(--c-primary); }
        .form-hint {
            font-size: 12px;
            color: var(--c-muted);
            margin-top: 6px;
        }
        .form-error {
            font-size: 12px;
            color: var(--c-danger);
            margin-top: 6px;
        }
        select.form-control { appearance: none; cursor: pointer; }

        /* ── Buttons ────────────────────────────────────── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 24px;
            border-radius: var(--c-radius-sm);
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            text-decoration: none;
            transition: all .15s;
        }
        .btn-primary {
            background: var(--c-primary);
            color: #fff;
        }
        .btn-primary:hover { background: var(--c-primary-h); }
        .btn-secondary {
            background: var(--c-surface2);
            border: 1px solid var(--c-border);
            color: var(--c-text);
        }
        .btn-secondary:hover { border-color: var(--c-primary); color: var(--c-primary); }
        .btn-danger { background: var(--c-danger); color: #fff; }
        .btn-success { background: var(--c-success); color: #fff; }
        .btn-lg { padding: 14px 32px; font-size: 16px; }
        .btn:disabled { opacity: .5; cursor: not-allowed; }

        /* ── Status Badge ───────────────────────────────── */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pass  { background: rgba(34,197,94,.15);  color: var(--c-success); }
        .status-warn  { background: rgba(245,158,11,.15); color: var(--c-warning); }
        .status-fail  { background: rgba(239,68,68,.15);  color: var(--c-danger);  }

        /* ── Alert ──────────────────────────────────────── */
        .alert {
            padding: 14px 18px;
            border-radius: var(--c-radius-sm);
            font-size: 14px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .alert-success { background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.25); color: var(--c-success); }
        .alert-warning { background: rgba(245,158,11,.1); border: 1px solid rgba(245,158,11,.25); color: var(--c-warning); }
        .alert-danger  { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.25); color: var(--c-danger); }
        .alert-info    { background: rgba(99,102,241,.1); border: 1px solid rgba(99,102,241,.25); color: var(--c-primary); }

        /* ── Check Row ──────────────────────────────────── */
        .check-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid var(--c-border);
        }
        .check-row:last-child { border-bottom: none; }
        .check-icon { font-size: 18px; flex-shrink: 0; }
        .check-label { font-weight: 500; font-size: 14px; min-width: 140px; }
        .check-message { color: var(--c-muted); font-size: 13px; flex: 1; }

        /* ── Code block ─────────────────────────────────── */
        .code-block {
            background: #0a0c14;
            border: 1px solid var(--c-border);
            border-radius: var(--c-radius-sm);
            padding: 16px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.7;
            color: #a5f3fc;
            overflow-x: auto;
            direction: ltr;
            text-align: left;
            white-space: pre-wrap;
        }

        /* ── Actions Bar ────────────────────────────────── */
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--c-border);
        }

        /* ── Mode Cards ─────────────────────────────────── */
        .mode-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 20px;
            margin-top: 32px;
        }
        .mode-card {
            background: var(--c-surface2);
            border: 2px solid var(--c-border);
            border-radius: var(--c-radius);
            padding: 28px;
            cursor: pointer;
            transition: all .2s;
            text-align: center;
        }
        .mode-card:hover { border-color: var(--c-primary); transform: translateY(-2px); }
        .mode-card.selected { border-color: var(--c-primary); background: rgba(99,102,241,.08); }
        .mode-icon {
            font-size: 40px;
            margin-bottom: 16px;
            display: block;
        }
        .mode-title { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .mode-desc { font-size: 13px; color: var(--c-muted); line-height: 1.5; }

        /* ── Responsive ─────────────────────────────────── */
        @media (max-width: 640px) {
            .installer-body { padding: 0 16px; margin: 24px auto; }
            .card { padding: 20px; }
            .progress-label { font-size: 10px; }
            .actions-bar { flex-direction: column-reverse; gap: 12px; }
            .mode-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <header class="installer-header">
        <div class="installer-logo">
            <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="32" height="32" rx="8" fill="#6366f1"/>
                <path d="M8 22 L16 10 L24 22" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                <circle cx="16" cy="22" r="2" fill="white"/>
            </svg>
            نصب‌کننده پلتفرم SaaS
        </div>
        <span class="installer-badge">v{{ config('app.version', '1.0.0') }}</span>
    </header>

    <main class="installer-body">

        @if (session('success'))
            <div class="alert alert-success">✓ {{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <div>
                    @foreach($errors->all() as $error)
                        <div>⚠ {{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        @yield('content')
    </main>

</body>
</html>
