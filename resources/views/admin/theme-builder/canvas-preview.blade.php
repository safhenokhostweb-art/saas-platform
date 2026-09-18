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

        {{-- TASK 3/3 Phase 6.5 — parity fix. Per-block VISUAL CSS is
             deliberately host-owned per ADR-029 (each host keeps its own
             copy), but this preview's copy had silently fallen far behind
             customer-site's: hero_split/hero_gradient/nav_header/nav_footer/
             promo_banner/countdown/wishlist/loyalty/installment_badge had
             ZERO rules here. Because Redis was down in this environment,
             ThemeCommercialQualityGate's browser-backed ResponsiveReadinessCheck
             was silently reporting "unverified" instead of actually running —
             once Redis was restored the check ran for real and caught this
             preview rendering catastrophic horizontal overflow (232px tablet,
             610px mobile) on the Universal Theme's own home page, purely
             because these blocks rendered with no styling at all. Mirrors
             customer-site's resources/views/layouts/canvas-theme.blade.php
             rules verbatim so both hosts' previews match. --}}
        .tb-hero-split { display:flex; align-items:center; gap:3rem; flex-wrap:wrap; }
        .tb-hero-split-text { flex:1 1 300px; }
        .tb-hero-split-visual { flex:1 1 300px; text-align:center; }
        .tb-hero-split-badge { display:inline-block; background:rgba(99,102,241,0.1); color:var(--tb-primary,#6366f1); font-size:0.82rem; font-weight:700; padding:0.3rem 0.9rem; border-radius:999px; border:1px solid rgba(99,102,241,0.25); margin-bottom:1rem; letter-spacing:0.03em; }
        .tb-hero-split-title { font-size:clamp(1.75rem,4vw,3.25rem); font-weight:800; line-height:1.1; margin:0 0 1rem; letter-spacing:-0.02em; color:var(--tb-text,#0f172a); }
        .tb-hero-split-sub { font-size:1.1rem; color:var(--tb-text-muted,#4b5563); line-height:1.75; margin:0 0 1.75rem; max-width:42ch; }
        .tb-hero-split-actions { display:flex; gap:0.75rem; flex-wrap:wrap; align-items:center; }
        .tb-hero-split-img { width:100%; max-width:520px; height:380px; border-radius:1.25rem; object-fit:cover; box-shadow:0 20px 60px rgba(0,0,0,0.15); }
        .tb-hero-split-img-placeholder { width:100%; max-width:520px; height:380px; border-radius:1.25rem; background:linear-gradient(135deg,var(--tb-primary,#6366f1),var(--tb-secondary,#0ea5e9)); display:flex; align-items:center; justify-content:center; font-size:6rem; box-shadow:0 20px 60px rgba(99,102,241,0.25); }
        @media(max-width:767px) { .tb-hero-split{flex-direction:column-reverse;gap:1.5rem;} .tb-hero-split-title{font-size:1.875rem;} .tb-hero-split-img,.tb-hero-split-img-placeholder{max-width:100%;height:220px;} }

        .tb-hero-gradient { text-align:center; padding:5.5rem 1.5rem; position:relative; overflow:hidden; }
        .tb-hero-gradient-inner { max-width:58rem; margin:0 auto; position:relative; z-index:1; }
        .tb-hero-gradient-badge { display:inline-block; background:rgba(255,255,255,0.18); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); border:1px solid rgba(255,255,255,0.3); font-size:0.82rem; font-weight:700; padding:0.3rem 1.1rem; border-radius:999px; margin-bottom:1.25rem; }
        .tb-hero-gradient-title { font-size:clamp(2rem,5.5vw,4.5rem); font-weight:800; line-height:1.05; margin:0 0 1.25rem; letter-spacing:-0.03em; }
        .tb-hero-gradient-sub { font-size:1.15rem; opacity:0.9; line-height:1.7; margin:0 auto; max-width:50ch; }
        @media(max-width:600px) { .tb-hero-gradient{padding:3.5rem 1rem;} .tb-hero-gradient-title{font-size:2rem;} }

        .tb-nav-header-inner { display: flex; align-items: center; gap: 1.5rem; min-height: 4.25rem; flex-wrap: wrap; padding: 0.5rem 0; }
        .tb-nav-header-logo { font-size: 1.35rem; font-weight: 800; text-decoration: none; color: inherit; letter-spacing: -0.01em; }
        .tb-nav-header-links { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1.5rem; margin-inline-start: auto; }
        .tb-nav-header-link { text-decoration: none; color: inherit; font-weight: 600; font-size: 0.95rem; opacity: 0.85; padding: 0.4rem 0; position: relative; transition: opacity 0.15s; }
        .tb-nav-header-link:hover { opacity: 1; }
        .tb-nav-header-link::after { content: ''; position: absolute; inset-inline: 0; bottom: -2px; height: 2px; background: currentColor; transform: scaleX(0); transition: transform 0.2s; transform-origin: center; }
        .tb-nav-header-link:hover::after { transform: scaleX(1); }
        {{-- TASK 3/3 Phase 6.5B — CSS parity only; nav_source=menu has
             nothing to render here by design. A Menu belongs to a real
             customer site (customer-site's own Menu/MenuItem models), and
             Master Theme authoring in this admin has no live customer site
             to preview against — $context['menu_html'] is correctly never
             populated on this side, so nav_source=menu previews as an
             intentionally empty nav until the theme is actually installed
             on a real site. --}}
        .tb-nav-header-links > a { text-decoration: none; color: inherit; font-weight: 600; font-size: 0.95rem; opacity: 0.85; padding: 0.4rem 0; }
        .tb-nav-header-links > a:hover { opacity: 1; }
        .tb-nav-header-actions { display: flex; align-items: center; gap: 0.85rem; flex-wrap: wrap; }
        .tb-nav-header-search { position: relative; }
        .tb-nav-header-search-input { padding: 0.5rem 2.25rem 0.5rem 0.9rem; border: 1px solid rgba(0,0,0,0.12); border-radius: 999px; font-family: inherit; font-size: 0.85rem; background: rgba(0,0,0,0.03); color: inherit; width: 11rem; max-width: 40vw; }
        .tb-nav-header-search-input::placeholder { color: currentColor; opacity: 0.55; }
        .tb-nav-header-phone { text-decoration: none; color: inherit; font-weight: 700; font-size: 0.9rem; opacity: 0.9; white-space: nowrap; }
        .tb-nav-header-icon-link { display: inline-flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: 999px; background: rgba(0,0,0,0.05); text-decoration: none; font-size: 1.05rem; transition: background 0.15s; }
        .tb-nav-header-icon-link:hover { background: rgba(0,0,0,0.1); }
        .tb-nav-header-cta { padding: 0.55rem 1.25rem; font-size: 0.9rem; white-space: nowrap; }
        @media (max-width: 1023px) { .tb-nav-header-search-input { width: 8rem; } }

        .tb-nav-footer .tb-container { padding-top: 3.5rem; padding-bottom: 2rem; }
        .tb-nav-footer-brand { margin-bottom: 2rem; }
        .tb-nav-footer-logo { font-size: 1.25rem; font-weight: 800; margin: 0 0 0.5rem; }
        .tb-nav-footer-tagline { font-size: 0.9rem; opacity: 0.75; line-height: 1.7; margin: 0; max-width: 32rem; }
        .tb-nav-footer-cols { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: 2rem; margin-bottom: 2.5rem; }
        .tb-nav-footer-col-title { font-size: 0.85rem; font-weight: 700; margin: 0 0 1rem; opacity: 0.6; text-transform: uppercase; letter-spacing: 0.04em; }
        .tb-nav-footer-links { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.7rem; }
        .tb-nav-footer-links a { text-decoration: none; color: inherit; opacity: 0.8; font-size: 0.9rem; transition: opacity 0.15s; }
        .tb-nav-footer-links a:hover { opacity: 1; }
        .tb-nav-footer-bottom { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding-top: 1.75rem; border-top: 1px solid rgba(255,255,255,0.1); }
        .tb-nav-footer-copyright { margin: 0; font-size: 0.85rem; opacity: 0.65; }
        .tb-nav-footer-legal-link { text-decoration: none; color: inherit; opacity: 0.7; font-size: 0.85rem; margin-inline-start: 1.25rem; }
        .tb-nav-footer-legal-link:hover { opacity: 1; }
        @media (max-width: 640px) {
            .tb-nav-footer .tb-container { padding-top: 2.5rem; }
            .tb-nav-footer-bottom { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
            .tb-nav-footer-legal-link { margin-inline-start: 0; margin-inline-end: 1rem; }
        }

        .tb-wishlist-link, .tb-loyalty-points-link { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.55rem 0.9rem; border-radius: 999px; background: var(--tb-surface, #fff); border: 1px solid #e5e7eb; color: var(--tb-text, #111827) !important; text-decoration: none; font-weight: 600; font-size: 0.85rem; transition: border-color 0.15s, background 0.15s; }
        .tb-wishlist-link:hover, .tb-loyalty-points-link:hover { border-color: var(--tb-primary, #4f46e5); background: var(--tb-surface, #f9fafb); }
        .tb-wishlist-link-icon, .tb-loyalty-points-link-icon { padding: 0.5rem; width: 2.5rem; height: 2.5rem; justify-content: center; }
        .tb-installment-badge { display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .tb-installment-badge-compact { padding: 0.6rem 1rem; border-radius: 0.5rem; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46 !important; font-weight: 700; font-size: 0.85rem; }
        .tb-installment-badge-icon { font-size: 1rem; }
        .tb-installment-badge.tb-empty-state { display: block; }
        .tb-installment-badge-full { display: block; padding: 1.25rem; border-radius: var(--tb-radius, 0.75rem); background: var(--tb-surface, #fff); border: 1px solid #e5e7eb; }
        .tb-installment-plans-list { display: flex; flex-direction: column; gap: 0.6rem; margin-bottom: 1rem; }
        .tb-installment-plan-row { display: flex; justify-content: space-between; gap: 1rem; padding: 0.6rem 0.75rem; border-radius: 0.5rem; background: var(--tb-background, #f9fafb); font-size: 0.85rem; }
        .tb-installment-plan-name { font-weight: 700; }
        .tb-installment-plan-detail { color: var(--tb-text-muted, #4b5563); }

        .tb-promo-banner { text-align:center; padding:0.85rem 1.25rem; }
        .tb-promo-banner__link { display:inline-block; font-weight:700; font-size:0.95rem; text-decoration:none; letter-spacing:0.01em; }
        .tb-promo-info    { background:var(--tb-surface,#f9fafb); border-bottom:1px solid #e5e7eb; }
        .tb-promo-info .tb-promo-banner__link    { color:var(--tb-text,#111827); }
        .tb-promo-sale     { background:linear-gradient(90deg,var(--tb-primary,#4f46e5),var(--tb-secondary,#6366f1)); }
        .tb-promo-sale .tb-promo-banner__link     { color:var(--tb-on-primary,#fff); }
        .tb-promo-urgent   { background:#dc2626; }
        .tb-promo-urgent .tb-promo-banner__link   { color:#fff; }

        .tb-countdown { text-align:center; padding:2.5rem 1.5rem; background:var(--tb-surface,#f9fafb); border-radius:var(--tb-radius,0.75rem); border:1px solid #e5e7eb; }
        .tb-countdown__label { display:block; font-weight:700; font-size:1.1rem; margin-bottom:1rem; color:var(--tb-text,#111827); }
        .tb-countdown__hint { display:inline-block; font-size:1.5rem; font-weight:800; font-variant-numeric:tabular-nums; color:var(--tb-primary-text,#4f46e5); letter-spacing:0.02em; }
        @media(max-width:600px) { .tb-countdown__hint { font-size:1.15rem; } }

        .tb-team-avatar { width:84px; height:84px; border-radius:50%; object-fit:cover; margin:1.75rem auto 1rem; border:3px solid var(--tb-primary,#4f46e5); display:block; }

        {{-- gallery block: had ZERO CSS on either host — see the matching
             comment in customer-site's canvas-theme.blade.php. This is what
             ThemeCommercialQualityGate's browser-backed overflow check was
             really catching. --}}
        .tb-gallery-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1.25rem; }
        .tb-gallery-cell { border-radius:0.75rem; overflow:hidden; }
        .tb-gallery-img { display:block; width:100%; height:16rem; object-fit:cover; }
        @media(max-width:480px) { .tb-gallery-grid { grid-template-columns:1fr; } .tb-gallery-img { height:12rem; } }

        {!! $css !!}
    </style>
    @foreach(($extAssets['css'] ?? []) as $__extHref)
    <link rel="stylesheet" href="{{ $__extHref }}">
    @endforeach
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

    @foreach(($extAssets['js'] ?? []) as $__extSrc)
    <script src="{{ $__extSrc }}" defer></script>
    @endforeach

</body>
</html>
