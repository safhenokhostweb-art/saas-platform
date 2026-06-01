@php
    /**
     * Theme Engine Token Injector
     * Emits a <style> block with CSS custom property overrides for the active ThemeProfile.
     * Injected AFTER design-tokens.css in <head> so the cascade overrides static defaults.
     * Site-aware: resolves tenant override if $currentSiteId is set in view context.
     */
    $siteId = $currentSiteId ?? null;
    $renderer = app(\App\Services\ThemeEngine\ThemeTokenRenderer::class);
    $cssBlock = $renderer->renderForSite($siteId);
@endphp
@if($cssBlock)
{!! $cssBlock !!}
@endif
