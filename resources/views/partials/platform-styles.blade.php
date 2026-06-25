@php
    $designSystem = app(\App\Services\DesignSystemService::class);
    $cssVars = $designSystem->getCssVariables(null);
    $fontImports = $designSystem->getFontImports(null);
@endphp
@foreach($fontImports as $url)
    <link rel="stylesheet" href="{{ $url }}" crossorigin="anonymous">
@endforeach
<style id="platform-theme">
:root {
@foreach($cssVars as $var => $value)
    {{ $var }}: {{ $value }};
@endforeach
}
body { font-family: var(--ds-font-body); font-size: var(--ds-font-size-base); }
h1, h2, h3, h4, h5, h6, .font-title { font-family: var(--ds-font-title); font-size: calc(1em * var(--ds-font-size-title)); color: var(--ds-title-color); }
.navbar { background: var(--ds-menu-bg) !important; }
.navbar-brand { color: var(--ds-primary) !important; }
.navbar-link { color: var(--ds-menu-text) !important; }
.navbar-link:hover, .navbar-link.text-blue-600 { color: var(--ds-menu-link-active) !important; }
.btn-primary { background: var(--ds-button-primary-bg) !important; color: var(--ds-button-primary-text) !important; }
/* فقط محتوای اصلی؛ نوار کناری و کاشی‌های داشبورد با سلکتور اختصاصی رنگ می‌گیرند */
main a { color: var(--ds-link-color); }
main a:hover { color: var(--ds-link-hover); }
/* هر لینک/دکمه‌ای که کلاس text-* صریح دارد نباید از رنگ لینک پلتفرم استفاده کند */
main a.text-white { color: #ffffff !important; }
main a.text-gray-100 { color: #f3f4f6 !important; }
main a.text-gray-200 { color: #e5e7eb !important; }
main a.text-gray-300 { color: #d1d5db !important; }
main a.text-gray-400 { color: #9ca3af !important; }
main a.text-gray-500 { color: #6b7280 !important; }
main a.text-gray-600 { color: #4b5563 !important; }
main a.text-gray-700 { color: #374151 !important; }
main a.text-gray-800 { color: #1f2937 !important; }
main a.text-gray-900 { color: #111827 !important; }
main a.text-slate-400 { color: #94a3b8 !important; }
main a.text-slate-500 { color: #64748b !important; }
main a.text-slate-600 { color: #475569 !important; }
main a.text-slate-700 { color: #334155 !important; }
main a.text-slate-800 { color: #1e293b !important; }
main a.text-slate-900 { color: #0f172a !important; }
main a.text-red-600 { color: #dc2626 !important; }
main a.text-emerald-600 { color: #059669 !important; }
main a.text-indigo-600 { color: #4f46e5 !important; }
main a.text-indigo-700 { color: #4338ca !important; }
.sidebar-link { color: var(--ds-sidebar-text); }
.sidebar-link:hover, .sidebar-link.active { background: var(--ds-sidebar-active-bg); color: var(--ds-sidebar-active-text); }
</style>
