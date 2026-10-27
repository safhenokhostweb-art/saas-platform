        {{-- Phase 2h (ADR-029): the shared structural/responsive/marquee
             CSS contract, identical to what customer-site's runtime
             includes — this used to be an independently maintained copy
             that silently drifted (Phase 2g found a missing grid
             breakpoint, a missing marquee clip, and an inconsistent
             muted-text color here). Do not re-add any of these rules
             locally; edit the shared package instead. --}}
        @include('theme-runtime::css.contract')
        {{-- Phase 0.3 (ADR-063): the same body rule as customer-site's live
             page (partials/theme-canvas-style-contract), so inherited text
             colour, background and font match; the fallbacks cover a preview
             rendered before its theme tokens are injected. --}}
        body {
            font-family: var(--tb-font-body, 'Vazirmatn', sans-serif);
            background: var(--tb-background, #ffffff);
            color: var(--tb-text, #1f2937);
            margin: 0;
            min-height: 100vh;
            font-size: var(--tb-body-size, 16px);
        }
        {{-- Phase 0.3 (ADR-063): every block's visual rules, shared with
             customer-site's live page. This preview used to keep its own
             drifted copy (hero, CTA, stats, cards and more rendered
             differently than on the live site). Do not add block rules here;
             edit the package's theme-runtime-blocks.css. --}}
        @include('theme-runtime::css.blocks')
