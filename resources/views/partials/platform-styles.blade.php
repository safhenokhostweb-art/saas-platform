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
.sidebar-link { color: var(--ds-sidebar-text); }
.sidebar-link:hover, .sidebar-link.active { background: var(--ds-sidebar-active-bg); color: var(--ds-sidebar-active-text); }
</style>
