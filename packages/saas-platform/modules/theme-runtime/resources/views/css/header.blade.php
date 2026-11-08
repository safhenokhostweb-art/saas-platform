{{-- ADR-082 step 2 — the nav_header layout alone, for a page that renders
     the header without the shared block sheet (theme-runtime::css.blocks
     already ships it). Include inside a <style> element, last in <head>:
     the header's former inline <style> came after every stylesheet. Edit
     ../../css/theme-runtime-header.css, never a copy. --}}
{!! file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::cssHeaderPath()) !!}
