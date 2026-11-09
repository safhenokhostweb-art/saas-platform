<?php

namespace SaaSPlatform\ThemeRuntime\Widget;

use SaaSPlatform\ThemeRuntime\BlockRegistry;

/**
 * ADR-082 parity: FooterStyleVars extracts CSS custom properties from the
 * nav_footer widget props and emits a scoped `--footer-*` variable block on
 * the widget root element. Mirrors the header's inline variable pattern
 * (--tb-header-*, --tb-logo-h, etc.) but with footer-specific prefix.
 *
 * Breakpoints align with the shared CSS contract:
 *   - desktop:   ≥1024px   (default)
 *   - tablet:    <1024px
 *   - mobile:    <768px
 *
 * The emitted style attribute contains ALL variables (desktop + tablet/mobile
 * overrides as separate --footer-*-tablet / --footer-*-mobile variables).
 * Responsive logic lives in nav-footer.css using @media queries that read
 * these variables.
 */
final class FooterStyleVars
{
    /** Spacing token map: registry SPACING_OPTIONS key => CSS value (rem). */
    private const SPACING_MAP = [
        'none' => '0',
        'xs'   => '0.25rem',
        'sm'   => '0.5rem',
        'md'   => '1rem',
        'lg'   => '1.5rem',
        'xl'   => '2rem',
    ];

    /**
     * Build the complete inline style attribute string for the footer root.
     * Contains ALL --footer-* variables (desktop + tablet/mobile overrides).
     * Responsive logic is in nav-footer.css using @media queries.
     *
     * @param array $props   The widget's resolved props (after defaults merge)
     * @param string $id     The widget instance ID (e.g. "footer-1")
     * @return string        Safe HTML style attribute
     */
    public static function build(array $props, string $id): string
    {
        $vars = self::collect($props);
        $inline = self::renderInline($vars);

        return 'style="' . $inline . '"';
    }

    /**
     * Collect all --footer-* variables from props, applying defaults and
     * normalizing values (spacing tokens → rem, colors → sanitized).
     *
     * @return array<string, string>  Variable name (without --) => value
     */
    private static function collect(array $props): array
    {
        $v = [];

        // ─── Layout / Grid ──────────────────────────────────────────────
        $v['footer-layout-columns']        = (string) max(1, min(12, (int) ($props['layout_columns'] ?? 4)));
        $v['footer-layout-gap']            = self::spacing($props['layout_gap'] ?? 'lg');
        $v['footer-layout-row-gap']        = self::spacing($props['layout_row_gap'] ?? 'md');
        $v['footer-layout-justify']        = self::justify($props['layout_justify'] ?? 'start');
        $v['footer-layout-align']          = self::align($props['layout_align'] ?? 'stretch');
        $v['footer-layout-wrap']           = $props['layout_wrap'] ?? true ? 'wrap' : 'nowrap';
        $v['footer-layout-direction']      = self::direction($props['layout_direction'] ?? 'row');

        // Tablet overrides
        $v['footer-layout-columns-tablet']    = (string) max(1, min(12, (int) ($props['layout_columns_tablet'] ?? 2)));
        $v['footer-layout-gap-tablet']        = self::spacing($props['layout_gap_tablet'] ?? 'md');
        $v['footer-layout-justify-tablet']    = self::justify($props['layout_justify_tablet'] ?? 'start');
        $v['footer-layout-align-tablet']      = self::align($props['layout_align_tablet'] ?? 'stretch');

        // Mobile overrides
        $v['footer-layout-columns-mobile']    = (string) max(1, min(12, (int) ($props['layout_columns_mobile'] ?? 1)));
        $v['footer-layout-gap-mobile']        = self::spacing($props['layout_gap_mobile'] ?? 'sm');
        $v['footer-layout-justify-mobile']    = self::justify($props['layout_justify_mobile'] ?? 'start');
        $v['footer-layout-align-mobile']      = self::align($props['layout_align_mobile'] ?? 'stretch');
        $v['footer-layout-stack-mobile']      = $props['layout_stack_mobile'] ?? true ? '1' : '0';
        $v['footer-reverse-columns-mobile']   = self::bool($props['reverse_columns_mobile'] ?? false);

        // ─── Section Visibility ────────────────────────────────────────
        $v['footer-show-brand']       = self::bool($props['show_brand'] ?? true);
        $v['footer-show-columns']     = self::bool($props['show_columns'] ?? true);
        $v['footer-show-bottom']      = self::bool($props['show_bottom'] ?? true);
        $v['footer-show-social']      = self::bool($props['show_social'] ?? true);
        $v['footer-show-newsletter']  = self::bool($props['show_newsletter'] ?? false);
        $v['footer-show-trust-seals'] = self::bool($props['show_trust_seals'] ?? false);
        $v['footer-show-app-badges']  = self::bool($props['show_app_badges'] ?? false);

        // ─── Responsive Visibility (per breakpoint) ────────────────────
        $v['footer-hide-on-desktop'] = self::bool($props['hide_on_desktop'] ?? false);
        $v['footer-hide-on-tablet']  = self::bool($props['hide_on_tablet'] ?? false);
        $v['footer-hide-on-mobile']  = self::bool($props['hide_on_mobile'] ?? false);

// ─── Colors ─────────────────────────────────────────────────────
        $v['footer-bg-color']           = self::color($props['bg_color'] ?? '#111827');
        $v['footer-text-color']         = self::color($props['text_color'] ?? '#e5e7eb');
        $v['footer-link-color']         = self::colorOrEmpty($props['link_color'] ?? '');
        $v['footer-link-hover-color']   = self::colorOrEmpty($props['link_hover_color'] ?? '');
        $v['footer-link-active-color']  = self::colorOrEmpty($props['link_active_color'] ?? '');
        $v['footer-link-focus-color']   = self::colorOrEmpty($props['link_focus_color'] ?? '');
        $v['footer-divider-color']      = self::colorOrEmpty($props['divider_color'] ?? '');
        $v['footer-divider-width']      = (string) max(0, min(4, (int) ($props['divider_width'] ?? 1))) . 'px';
        $v['footer-divider-style']      = self::dividerStyle($props['divider_style'] ?? 'solid');
        $v['footer-divider-margin-top'] = (string) max(0, (int) ($props['divider_margin_top'] ?? 28)) . 'px';
        $v['footer-divider-margin-bottom'] = (string) max(0, (int) ($props['divider_margin_bottom'] ?? 28)) . 'px';

        // Background
        $v['footer-bg-type']            = self::bgType($props['background_type'] ?? 'color');
        $v['footer-gradient-from']      = self::colorOrEmpty($props['gradient_from'] ?? '');
        $v['footer-gradient-to']        = self::colorOrEmpty($props['gradient_to'] ?? '');
        $v['footer-gradient-angle']     = (string) max(0, min(360, (int) ($props['gradient_angle'] ?? 180))) . 'deg';
        $v['footer-bg-image']           = self::urlOrEmpty($props['bg_image'] ?? '');
        $v['footer-bg-image-position']  = self::bgPosition($props['bg_image_position'] ?? 'center');
        $v['footer-bg-image-size']      = self::bgSize($props['bg_image_size'] ?? 'cover');
        $v['footer-bg-image-repeat']    = self::bgRepeat($props['bg_image_repeat'] ?? 'no-repeat');
        $v['footer-bg-image-attachment']= self::bgAttachment($props['bg_image_attachment'] ?? 'scroll');

        // ─── Column Title Typography ─────────────────────────────
        $v['footer-col-title-font-size']      = (string) max(8, min(24, (float) ($props['col_title_font_size'] ?? 13.6))) . 'px';
        $v['footer-col-title-font-weight']    = $props['col_title_font_weight'] ?? '700';
        $v['footer-col-title-text-transform'] = self::textTransform($props['col_title_text_transform'] ?? 'uppercase');
        $v['footer-col-title-letter-spacing'] = (string) max(0, min(0.5, (float) ($props['col_title_letter_spacing'] ?? 0.04))) . 'em';
        $v['footer-col-title-color']          = self::colorOrEmpty($props['col_title_color'] ?? '');
        $v['footer-col-title-opacity']        = (string) max(0, min(1, (float) ($props['col_title_opacity'] ?? 0.6)));
        $v['footer-col-title-margin-bottom']  = (string) max(0, (int) ($props['col_title_margin_bottom'] ?? 16)) . 'px';

        // ─── Spacing (padding) ─────────────────────────────────────────
        $v['footer-padding-top']        = (string) max(0, (int) ($props['padding_top'] ?? 80)) . 'px';
        $v['footer-padding-right']      = (string) max(0, (int) ($props['padding_right'] ?? 24)) . 'px';
        $v['footer-padding-bottom']     = (string) max(0, (int) ($props['padding_bottom'] ?? 48)) . 'px';
        $v['footer-padding-left']       = (string) max(0, (int) ($props['padding_left'] ?? 24)) . 'px';

        $v['footer-padding-top-tablet']    = (string) max(0, (int) ($props['padding_top_tablet'] ?? 60)) . 'px';
        $v['footer-padding-bottom-tablet'] = (string) max(0, (int) ($props['padding_bottom_tablet'] ?? 40)) . 'px';
        $v['footer-padding-top-mobile']    = (string) max(0, (int) ($props['padding_top_mobile'] ?? 48)) . 'px';
        $v['footer-padding-bottom-mobile'] = (string) max(0, (int) ($props['padding_bottom_mobile'] ?? 32)) . 'px';

        // ─── Bottom Bar Styling ─────────────────────────────────
        $v['footer-bottom-copyright-font-size']      = (string) max(8, min(20, (float) ($props['bottom_copyright_font_size'] ?? 13.6))) . 'px';
        $v['footer-bottom-copyright-opacity']        = (string) max(0, min(1, (float) ($props['bottom_copyright_opacity'] ?? 0.65)));
        $v['footer-bottom-copyright-line-height']    = (string) max(1, min(2.5, (float) ($props['bottom_copyright_line_height'] ?? 1.5)));
        $v['footer-bottom-legal-font-size']          = (string) max(8, min(20, (float) ($props['bottom_legal_font_size'] ?? 13.6))) . 'px';
        $v['footer-bottom-legal-opacity']            = (string) max(0, min(1, (float) ($props['bottom_legal_opacity'] ?? 0.7)));
        $v['footer-bottom-legal-margin']             = (string) max(0, (int) ($props['bottom_legal_margin'] ?? 20)) . 'px';
        $v['footer-bottom-padding-top']              = (string) max(0, (int) ($props['bottom_padding_top'] ?? 28)) . 'px';
        $v['footer-bottom-gap']                      = (string) max(0, (int) ($props['bottom_gap'] ?? 16)) . 'px';

        // ─── Borders / Shadow / Radius ─────────────────────────────────
        $v['footer-border-top']         = self::bool($props['border_top'] ?? false);
        $v['footer-border-top-color']   = self::colorOrEmpty($props['border_top_color'] ?? '');
        $v['footer-border-top-width']   = (string) max(0, min(4, (int) ($props['border_top_width'] ?? 1))) . 'px';
        $v['footer-border-radius']      = (string) max(0, min(48, (int) ($props['border_radius'] ?? 0))) . 'px';

        $v['footer-box-shadow']         = self::bool($props['box_shadow'] ?? false);
        $v['footer-box-shadow-color']   = self::colorOrEmpty($props['box_shadow_color'] ?? '');
        $v['footer-box-shadow-blur']    = (string) max(0, min(80, (int) ($props['box_shadow_blur'] ?? 20))) . 'px';
        $v['footer-box-shadow-spread']  = (string) max(-20, min(20, (int) ($props['box_shadow_spread'] ?? -4))) . 'px';

        // ─── Behavior ──────────────────────────────────────────────────
        $v['footer-position-mode']      = self::positionMode($props['position_mode'] ?? 'static');
        $v['footer-z-index']            = (string) max(0, min(9999, (int) ($props['z_index'] ?? 10)));
        $v['footer-margin-top']         = (string) max(-200, min(200, (int) ($props['margin_top'] ?? 0))) . 'px';

        return $v;
    }

    /** Render all variables as inline style (no -- prefix here). */
    private static function renderInline(array $vars): string
    {
        $out = [];
        foreach ($vars as $name => $value) {
            if ($value === '' || $value === null) {
                continue;
            }
            $out[] = '--' . $name . ':' . $value;
        }
        return implode(';', $out);
    }

    // ─── Normalizers ───────────────────────────────────────────────────

    private static function spacing(string $token): string
    {
        return self::SPACING_MAP[$token] ?? '1rem';
    }

    private static function justify(string $value): string
    {
        $map = [
            'start' => 'flex-start',
            'center' => 'center',
            'end' => 'flex-end',
            'space-between' => 'space-between',
            'space-around' => 'space-around',
            'space-evenly' => 'space-evenly',
        ];
        return $map[$value] ?? 'flex-start';
    }

    private static function align(string $value): string
    {
        $map = [
            'stretch' => 'stretch',
            'flex-start' => 'flex-start',
            'center' => 'center',
            'flex-end' => 'flex-end',
        ];
        return $map[$value] ?? 'stretch';
    }

    private static function direction(string $value): string
    {
        return $value === 'column' ? 'column' : 'row';
    }

    private static function dividerStyle(string $value): string
    {
        $allowed = ['solid', 'dashed', 'dotted', 'double'];
        return in_array($value, $allowed, true) ? $value : 'solid';
    }

    private static function bgType(string $value): string
    {
        $allowed = ['color', 'gradient', 'image'];
        return in_array($value, $allowed, true) ? $value : 'color';
    }

    private static function bgPosition(string $value): string
    {
        $allowed = ['center', 'top', 'bottom', 'left', 'right', 'cover'];
        return in_array($value, $allowed, true) ? $value : 'center';
    }

    private static function bgSize(string $value): string
    {
        $allowed = ['cover', 'contain', 'auto'];
        return in_array($value, $allowed, true) ? $value : 'cover';
    }

    private static function bgRepeat(string $value): string
    {
        $allowed = ['no-repeat', 'repeat', 'repeat-x', 'repeat-y'];
        return in_array($value, $allowed, true) ? $value : 'no-repeat';
    }

    private static function bgAttachment(string $value): string
    {
        return $value === 'fixed' ? 'fixed' : 'scroll';
    }

    private static function positionMode(string $value): string
    {
        $allowed = ['static', 'sticky-bottom'];
        return in_array($value, $allowed, true) ? $value : 'static';
    }

    private static function color(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '#111827';
        }
        // Allow CSS variables, hex, rgb, hsl, named colors
        if (preg_match('/^(var\(|#[0-9a-f]{3,8}|rgb\(|rgba\(|hsl\(|hsla\(|[a-z]+$)/i', $v)) {
            return $v;
        }
        return '#111827';
    }

    private static function colorOrEmpty(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }
        if (preg_match('/^(var\(|#[0-9a-f]{3,8}|rgb\(|rgba\(|hsl\(|hsla\(|[a-z]+$)/i', $v)) {
            return $v;
        }
        return '';
    }

    private static function urlOrEmpty(mixed $value): string
    {
        // Handle media field arrays like ['media_id' => '123'] or ['url' => '...']
        if (is_array($value)) {
            $value = $value['url'] ?? '';
        }
        $v = trim((string) $value);
        if ($v === '') {
            return '';
        }
        // Basic URL validation - allow relative, absolute, data URIs
        if (preg_match('/^(https?:)?\/\/|\.|\/|data:/i', $v)) {
            return $v;
        }
        return '';
    }

    private static function bool(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
    }

    private static function textTransform(string $value): string
    {
        $allowed = ['none', 'uppercase', 'lowercase', 'capitalize'];
        return in_array($value, $allowed, true) ? $value : 'none';
    }
}