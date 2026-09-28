<!DOCTYPE html>
@php
    $seoService = app(\App\Services\Seo\SeoService::class);
    $metaData = $seoService->resolveForPreview($page, [
        'title' => $page['title'] ?? $pageKey,
        'meta_title' => $page['title'] ?? $pageKey,
    ]);
    $metaArray = $metaData->toArray();
@endphp
<html lang="{{ $metaArray['language'] }}" dir="{{ $metaArray['direction'] }}">
<head>
    <meta charset="UTF-8">
    {{-- Commercial Productization fase-4: identical head-bootstrap script
         to customer-site's layouts/canvas-theme.blade.php — SaaS preview
         and customer runtime must behave the same for dark mode, including
         the anti-flash-of-wrong-theme guarantee. See that file's comment
         for the full rationale; window.__tbTheme is the shared API
         BlockRenderer::renderDarkModeToggle() talks to. --}}
    <script>(function(){
        var KEY = 'tb-theme';
        function resolve(p) {
            if (p === 'system') {
                try { return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'; }
                catch (e) { return 'light'; }
            }
            return p === 'dark' ? 'dark' : 'light';
        }
        function apply(p) {
            document.documentElement.setAttribute('data-theme', resolve(p));
            document.documentElement.setAttribute('data-theme-pref', p);
        }
        var pref = 'system';
        try { pref = localStorage.getItem(KEY) || 'system'; } catch (e) {}
        apply(pref);
        window.__tbTheme = {
            get: function () { return pref; },
            set: function (p) {
                pref = (p === 'light' || p === 'dark' || p === 'system') ? p : 'system';
                apply(pref);
                try { localStorage.setItem(KEY, pref); } catch (e) {}
                document.dispatchEvent(new CustomEvent('tb-theme-change', { detail: { pref: pref, resolved: resolve(pref) } }));
            },
            cycle: function () {
                var order = ['light', 'dark', 'system'];
                this.set(order[(order.indexOf(pref) + 1) % order.length]);
            }
        };
        try {
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
                if (pref === 'system') { apply('system'); document.dispatchEvent(new CustomEvent('tb-theme-change', { detail: { pref: pref, resolved: resolve(pref) } })); }
            });
        } catch (e) {}
    })();</script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <x-seo-meta :meta-data="$metaData" />

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        {{-- Canvas fidelity fix (2026-09-20): this file's own comprehensive,
             parity-audited component CSS used to live only here, so the
             TB3-02 live iframe (canvas-live-preview.blade.php) — the view
             actually shown inside the Canvas editor's WYSIWYG iframe — had
             silently drifted to an incomplete, stale subset (missing
             nav_header/nav_footer, hero_split/hero_gradient, gallery,
             promo_banner/countdown, wishlist/installment CSS entirely).
             Extracted verbatim into partials/canvas-css-contract.blade.php
             so both this static full-page preview and the live editor
             iframe share one single, un-driftable source. Do not re-add any
             of these rules locally in either file; edit the partial. --}}
        @include('admin.theme-builder.partials.canvas-css-contract')
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