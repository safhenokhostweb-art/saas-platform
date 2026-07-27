# Browser QA Plan

No browser automation is implemented in this phase. These scenarios are mandatory acceptance gates for the future integration candidate.

## Matrix

Test the current and previous major versions of Chrome, Firefox, and Safari on desktop, representative tablet widths, and representative mobile widths. Include iOS Safari and Android Chrome on physical devices or an approved device service.

## Scenarios

For every representative engine and asset complexity tier, verify:

1. first render, loop continuity, pause/resume, tab background/foreground, and cleanup;
2. RTL and LTR containers, Persian text direction, alignment, clipping, and mirroring rules;
3. responsive viewBox/aspect ratio at 320, 375, 768, 1024, and 1440 CSS pixels;
4. light/dark/custom themes and contrast;
5. `prefers-reduced-motion: reduce`, keyboard navigation, screen-reader naming, and focus behavior;
6. slow network, cache hit/miss, offline failure, and denied/expired asset URL;
7. two and five simultaneous animations without layout shift or runaway CPU;
8. CSP with network-disabled SVG and no external dependency;
9. tenant switch/logout ensuring private content is not retained; and
10. visual regression against an approved baseline.

Capture browser/device, OS, asset/version/checksum, viewport, theme, result, screenshot/video evidence, timing, memory/CPU notes, accessibility findings, and reviewer approval. Any security failure, source exposure, broken reduced motion, or cross-tenant result is a release blocker.

