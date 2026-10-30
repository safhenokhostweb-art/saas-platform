<?php

namespace Tests\Support\FoodMart;

/**
 * Phase 2B — byte-exact static CSS extracted (read-only) from
 * customer-site/resources/views/partials/theme-canvas-style-contract.blade.php,
 * with only its three dynamic insertion points replaced by the markers
 * /*__THEME_TOKEN_CSS__*\/, /*__THEME_RUNTIME_CONTRACT_CSS__*\/ and
 * /*__THEME_RUNTIME_BLOCKS_CSS__*\/ (the harness substitutes real computed
 * content for each at build time). Every other byte — the \`body{}\` rule,
 * the host chrome, the `*:focus-visible` rule — is exactly what the real
 * customer-site page ships today.
 *
 * Phase 0.3 (ADR-063): the block rules moved from that partial into the
 * shared package stylesheet, hence the third marker.
 *
 * customer-site was only READ to produce this constant; it was never
 * modified. This file exists purely so the Phase 2B test-only harness can
 * reproduce the real shell's CSS without a live cross-app render.
 */
final class FoodMartPhase2bStyleContract
{
    public const CSS = <<<'__FOODMART_P2B_EOF__'
        
        /*__THEME_TOKEN_CSS__*/
        :root { --platform-primary: var(--tb-primary); }

        
        /*__THEME_RUNTIME_CONTRACT_CSS__*/

        body {
            font-family: var(--tb-font-body);
            background: var(--tb-background);
            color: var(--tb-text);
            margin: 0;
            min-height: 100vh;
            font-size: var(--tb-body-size, 16px);
        }

        /* ─── هدر canvas ─────────────────────────────────────────────── */
        .canvas-site-header {
            background: var(--tb-surface, #fff);
            border-bottom: 1px solid rgba(0,0,0,0.08);
            box-shadow: var(--tb-shadow-sm);
        }
        .canvas-site-header .tb-site-header {
            border-bottom: none;
            box-shadow: none;
        }

        /* ─── محتوای اصلی ────────────────────────────────────────────── */
        .canvas-main {
            min-height: 60vh;
        }

        /* ─── فوتر canvas ────────────────────────────────────────────── */
        .canvas-site-footer {
            background: var(--tb-surface, #fff);
            border-top: 1px solid #e5e7eb;
        }

        
        /*__THEME_RUNTIME_BLOCKS_CSS__*/

        /* skip link */
        .canvas-skip-link { position: fixed; top: -5rem; right: 1rem; z-index: 999; padding: 0.5rem 1rem; background: #1f2937; color: #fff; border-radius: 0.5rem; text-decoration: none; }
        .canvas-skip-link:focus { top: 1rem; }
        *:focus-visible { outline: 2px solid var(--tb-primary, #4f46e5); outline-offset: 2px; }

        /* اعلان‌ها */
        .tb-notifications-bar { margin: 0; }

        /* ─── NEXUS PRO: Skills bar animation on scroll ─────────────── */
        
        .tb-skill-bar-fill[data-width] { width:0 !important; }
        .tb-skill-bar-fill.animated { width:var(--skill-w) !important; }

        
        .visually-hidden {
            position:absolute !important;
            width:1px !important;
            height:1px !important;
            padding:0 !important;
            margin:-1px !important;
            overflow:hidden !important;
            clip:rect(0,0,0,0) !important;
            clip-path:inset(50%) !important;
            white-space:nowrap !important;
            border:0 !important;
        }
__FOODMART_P2B_EOF__;
}
