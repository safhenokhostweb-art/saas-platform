{{-- Phase 0.3 (ADR-063) — the shared block stylesheet, included verbatim by
     both host style partials. Edit the source at
     ../../css/theme-runtime-blocks.css, never a copy. Path resolved via the
     service provider for the same reason as css/contract.blade.php. --}}
{!! file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::cssBlocksPath()) !!}
