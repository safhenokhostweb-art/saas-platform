{{--
    Phase 18 — Part 8: Customer Runtime rendering component.
    Usage from a theme (once a maintainer chooses to wire it in — this
    module does not modify any Core Blade file itself, see the Phase 18
    report Part 5 for why):
        <x-seo-advanced::head :site-id="$site->id" />
--}}
@php
    $__seoAdvancedHead = app(\SaaSPlatform\Modules\SeoAdvanced\Contracts\SeoHeadRendererInterface::class)
        ->render($siteId, $overrides ?? []);
@endphp
{!! $__seoAdvancedHead !!}
