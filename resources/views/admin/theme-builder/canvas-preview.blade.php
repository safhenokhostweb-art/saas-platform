<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پیش‌نمایش: {{ $page['title'] ?? $pageKey }} — {{ $theme->name }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        {{-- Phase 2h (ADR-029): the shared structural/responsive/marquee
             CSS contract, identical to what customer-site's runtime
             includes — this used to be an independently maintained copy
             that silently drifted (Phase 2g found a missing grid
             breakpoint, a missing marquee clip, and an inconsistent
             muted-text color here). Do not re-add any of these rules
             locally; edit the shared package instead. --}}
        @include('theme-runtime::css.contract')
        body { font-family: 'Vazirmatn', sans-serif; margin: 0; padding: 0; direction: rtl; background: #ffffff; color: #1f2937; }
        {{-- Phase 2k: was fully hardcoded (#4f46e5/#fff, no var() at all) —
             a real, previously-undiscovered parity gap: the SaaS Quality
             Gate's own browser contrast check always rendered this same
             safe default color regardless of a Theme's actual primary
             color, so it could never detect the real WCAG failures 17
             canonical themes had on customer-site's runtime (which WAS
             token-bound). Token-bound now, consuming the same
             ThemeTokenNormalizer-derived --tb-on-primary/--tb-primary-text
             both hosts share. --}}
        .tb-hero          { min-height: 420px; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; padding: 5rem 1.5rem; background: var(--tb-primary, #4f46e5); color: var(--tb-on-primary, #fff); }
        .tb-hero h1       { font-size: 2.5rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.3; }
        .tb-hero p        { font-size: 1.1rem; opacity: 0.85; margin-bottom: 1.5rem; }
        .tb-hero .tb-btn  { display: inline-flex; align-items: center; padding: 0.75rem 2rem; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.6); color: inherit; border-radius: 0.5rem; font-size: 1rem; font-weight: 600; text-decoration: none; }
        .tb-cta           { padding: 4rem 1.5rem; text-align: center; background: var(--tb-primary, #4f46e5); color: var(--tb-on-primary, #fff); }
        .tb-cta h2        { font-size: 1.8rem; font-weight: 800; margin-bottom: 0.75rem; }
        .tb-cta p         { font-size: 1rem; opacity: 0.85; margin-bottom: 1.5rem; }
        .tb-cta a         { display: inline-flex; padding: 0.65rem 1.75rem; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.6); color: inherit; border-radius: 0.5rem; font-weight: 600; text-decoration: none; }
        .tb-heading       { line-height: 1.4; margin-bottom: 1rem; font-weight: 700; }
        .tb-text          { line-height: 1.85; color: #374151; margin-bottom: 1rem; }
        .tb-button        { display: inline-flex; align-items: center; padding: 0.6rem 1.5rem; border-radius: 0.5rem; font-weight: 600; font-size: 0.9rem; text-decoration: none; }
        .tb-btn-primary   { background: var(--tb-primary, #4f46e5); color: var(--tb-on-primary, #fff); }
        .tb-bg-primary    { background: var(--tb-primary, #4f46e5); color: var(--tb-on-primary, #fff); }
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
