{{-- Phase 0.4 (ADR-063) — the local Vazirmatn @font-face rules both hosts
     ship (no third-party font CDN: unreliable from Iran and a source of
     FOUT). Include inside a <style> element; the .woff2 files are served by
     each host from public/assets/fonts/vazirmatn/. Edit
     ../../css/vazirmatn-font-faces.css, never a copy. --}}
{!! file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::fontFacesPath()) !!}
