{{-- Phase 0.3 (ADR-063) — the shared block stylesheet, included verbatim by
     both host style partials. Edit the source at
     ../../css/theme-runtime-blocks.css, never a copy. Path resolved via the
     service provider for the same reason as css/contract.blade.php.

     ADR-082 step 2: the nav_header layout (../../css/theme-runtime-header.css)
     follows the block rules, the place in the cascade its former inline
     <style> held, so both host partials ship it without a change. --}}
{!! file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::cssBlocksPath()) !!}
{!! file_get_contents(\SaaSPlatform\ThemeRuntime\Providers\ThemeRuntimeServiceProvider::cssHeaderPath()) !!}
