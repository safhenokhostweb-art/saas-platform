<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیش‌نمایش: {{ $page['title'] ?? $pageKey }} — {{ $theme->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Vazirmatn', sans-serif; margin: 0; padding: 0; direction: rtl; background: #ffffff; color: #1f2937; }
        .tb-container { max-width: 1200px; margin: 0 auto; padding: 0 1.5rem; }
        .tb-section { width: 100%; }
        .tb-section-inner { width: 100%; }
        .tb-padding-none  { padding-top: 0; padding-bottom: 0; }
        .tb-padding-sm    { padding-top: 2rem; padding-bottom: 2rem; }
        .tb-padding-md    { padding-top: 4rem; padding-bottom: 4rem; }
        .tb-padding-lg    { padding-top: 6rem; padding-bottom: 6rem; }
        .tb-padding-xl    { padding-top: 8rem; padding-bottom: 8rem; }
        .tb-grid          { display: grid; gap: 1.5rem; }
        .tb-cols-2        { grid-template-columns: repeat(2, 1fr); }
        .tb-cols-3        { grid-template-columns: repeat(3, 1fr); }
        .tb-cols-4        { grid-template-columns: repeat(4, 1fr); }
        .tb-hero          { min-height: 420px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 5rem 1.5rem; }
        .tb-hero h1       { font-size: 2.5rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.3; }
        .tb-hero p        { font-size: 1.1rem; opacity: 0.85; margin-bottom: 1.5rem; }
        .tb-hero .tb-btn  { display: inline-flex; align-items: center; padding: 0.75rem 2rem; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.6); color: #fff; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; text-decoration: none; }
        .tb-cta           { padding: 4rem 1.5rem; text-align: center; }
        .tb-cta h2        { font-size: 1.8rem; font-weight: 800; margin-bottom: 0.75rem; }
        .tb-cta p         { font-size: 1rem; opacity: 0.85; margin-bottom: 1.5rem; }
        .tb-cta a         { display: inline-flex; padding: 0.65rem 1.75rem; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.6); color: #fff; border-radius: 0.5rem; font-weight: 600; text-decoration: none; }
        .tb-heading       { line-height: 1.4; margin-bottom: 1rem; font-weight: 700; }
        .tb-text          { line-height: 1.85; color: #374151; margin-bottom: 1rem; }
        .tb-img           { max-width: 100%; border-radius: 0.5rem; }
        .tb-img-center    { display: block; margin: 0 auto; }
        .tb-img-right     { display: block; margin-right: 0; }
        .tb-img-full      { width: 100%; }
        .tb-button        { display: inline-flex; align-items: center; padding: 0.6rem 1.5rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.9rem; text-decoration: none; }
        .tb-btn-primary   { background: #4f46e5; color: #fff; }
        .tb-divider       { border: none; border-top: 1px solid #e5e7eb; margin: 1rem 0; }
        .tb-bg-primary    { background: #4f46e5; color: #fff; }
        .tb-bg-dark       { background: #1e293b; color: #fff; }
        .tb-bg-light      { background: #f8fafc; }
        .tb-bg-gray       { background: #f1f5f9; }
        {!! $css !!}
    </style>
</head>
<body>

    {{-- Preview Topbar — suppressed when rendering for a marketplace screenshot,
         so the captured image shows only the theme's real content. --}}
    @unless($hideChrome ?? false)
    <div style="position:fixed;top:0;left:0;right:0;z-index:9999;background:#1e293b;color:#fff;padding:10px 20px;display:flex;align-items:center;justify-content:space-between;font-family:'Vazirmatn',sans-serif;font-size:13px;direction:rtl;">
        <div style="display:flex;align-items:center;gap:12px;">
            <span style="background:#4f46e5;color:#fff;padding:3px 10px;border-radius:4px;font-size:11px;font-weight:600;">پیش‌نمایش</span>
            <span style="color:#94a3b8;">{{ $theme->name }}</span>
            <span style="color:#64748b;">←</span>
            <span style="color:#e2e8f0;font-weight:600;">{{ $page['title'] ?? $pageKey }}</span>
        </div>
        <div style="display:flex;gap:8px;">
            <a href="{{ route('admin.theme-builder.canvas', $theme) }}" style="color:#94a3b8;text-decoration:none;font-size:12px;padding:4px 10px;border:1px solid #334155;border-radius:5px;hover:color:#fff;">← بازگشت به قالب‌ساز</a>
            <button onclick="window.close()" style="color:#94a3b8;background:none;border:1px solid #334155;border-radius:5px;padding:4px 10px;font-size:12px;cursor:pointer;">بستن</button>
        </div>
    </div>
    @endunless

    {{-- Page Content --}}
    <div style="padding-top: {{ ($hideChrome ?? false) ? '0' : '44px' }};">
        {!! $html !!}

        @if(empty($page['blocks'] ?? []))
            <div style="text-align:center;padding:5rem 2rem;color:#9ca3af;">
                <div style="font-size:3rem;margin-bottom:1rem;">🧩</div>
                <p style="font-size:1rem;font-weight:600;margin-bottom:0.5rem;">صفحه خالی است</p>
                <p style="font-size:0.85rem;">از قالب‌ساز بلوک اضافه کنید تا اینجا نمایش داده شود</p>
            </div>
        @endif
    </div>

</body>
</html>
