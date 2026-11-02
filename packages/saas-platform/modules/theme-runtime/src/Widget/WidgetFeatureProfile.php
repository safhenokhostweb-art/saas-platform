<?php

namespace SaaSPlatform\ThemeRuntime\Widget;

/**
 * ADR-077: the commercial features each widget kind must show on a live
 * page, checked by the Sentinel UI verifier (saas-platform tests/sentinel).
 *
 * A requirement is a CSS selector evaluated inside the rendered widget
 * (`[data-widget-type="<type>"]`). When it matches nothing, the verifier
 * reports `code` at `severity` with `remedy` as the task to do. Selectors
 * prefer the ADR-076 slot hooks (`data-widget-slot`) so they follow the
 * schema, not incidental markup.
 *
 * export() is written to tests/sentinel/widget-profiles.json by
 * `php artisan sentinel:profiles`. WidgetFeatureProfileTest keeps that file
 * current and checks every type and slot named here against the registry
 * manifest, so a profile can never require a slot a widget does not declare.
 */
final class WidgetFeatureProfile
{
    public const SCHEMA = 'sentinel.widget-profiles/v1';
    public const SEVERITIES = ['info', 'warning', 'blocker'];

    /** Applied to every widget on the page. */
    public const GLOBAL = [
        ['code' => 'DEAD_LINK', 'severity' => 'warning', 'forbid' => 'a[href="#"]:not([role="button"]):not([aria-haspopup])',
            'message' => 'The widget shows a link that goes nowhere (href="#").',
            'remedy' => 'Give the link a real destination in the widget settings, or remove it (Constitution §16, honest interactions).'],
    ];

    /** type => list of requirements (selector, code, severity, message, remedy). */
    public const PROFILES = [
        'nav_header' => [
            ['selector' => '[data-widget-slot="menu"]', 'code' => 'FEATURE_MISSING_MENU', 'severity' => 'blocker',
                'message' => 'The header renders no menu slot.', 'remedy' => 'Render the menu slot (nav_header.nav_links or nav_source=menu).'],
            ['selector' => '[data-widget-slot="menu"] a[href]', 'code' => 'FEATURE_EMPTY_MENU', 'severity' => 'warning',
                'message' => 'The header menu has no links.', 'remedy' => 'Add menu links in the header settings or assign the site menu (nav_source=menu).'],
            ['selector' => '[data-widget-slot="logo"], img, .site-logo, [class*="brand"]', 'code' => 'FEATURE_MISSING_LOGO', 'severity' => 'warning',
                'message' => 'The header shows no logo or brand.', 'remedy' => 'Upload a logo image (nav_header.logo_image), set logo_text, or set the site logo.'],
            ['selector' => '[data-widget-slot="actions"]', 'code' => 'FEATURE_MISSING_ACTIONS', 'severity' => 'blocker',
                'message' => 'The header renders no actions slot.', 'remedy' => 'Render the actions slot (search, cart, account, quick-access icons, CTA).'],
        ],
        'nav_footer' => [
            ['selector' => '[data-widget-slot="columns"] a[href], .tb-nav-footer-legal-link', 'code' => 'FEATURE_MISSING_FOOTER_LINKS', 'severity' => 'warning',
                'message' => 'The footer has no navigation or legal links.', 'remedy' => 'Add link columns or legal links (nav_footer.columns / links), or use nav_source=menu.'],
            ['selector' => '[data-widget-slot="social"] a[href]', 'code' => 'FEATURE_MISSING_SOCIAL', 'severity' => 'info',
                'message' => 'The footer lists no social or messenger channels.', 'remedy' => 'Add social links (nav_footer.social_links); Iranian messengers have their own icons.'],
            ['selector' => '.tb-nav-footer-copyright', 'code' => 'FEATURE_MISSING_COPYRIGHT', 'severity' => 'info',
                'message' => 'The footer has no copyright line.', 'remedy' => 'Set nav_footer.copyright.'],
        ],
        'hero' => [
            ['selector' => 'a[href]:not([href="#"]):not([href=""])', 'code' => 'FEATURE_MISSING_CTA', 'severity' => 'warning',
                'message' => 'The hero has no call-to-action with a real destination.', 'remedy' => 'Set the hero button text and link.'],
        ],
        'hero_split' => [
            ['selector' => 'a[href]:not([href="#"]):not([href=""])', 'code' => 'FEATURE_MISSING_CTA', 'severity' => 'warning',
                'message' => 'The hero has no call-to-action with a real destination.', 'remedy' => 'Set the hero button text and link.'],
        ],
        'hero_gradient' => [
            ['selector' => 'a[href]:not([href="#"]):not([href=""])', 'code' => 'FEATURE_MISSING_CTA', 'severity' => 'warning',
                'message' => 'The hero has no call-to-action with a real destination.', 'remedy' => 'Set the hero button text and link.'],
        ],
        'cta' => [
            ['selector' => 'a[href]:not([href="#"]):not([href=""])', 'code' => 'FEATURE_MISSING_CTA', 'severity' => 'warning',
                'message' => 'The call-to-action has no real destination.', 'remedy' => 'Set the button link of the CTA block.'],
        ],
        'cta_band' => [
            ['selector' => 'a[href]:not([href="#"]):not([href=""])', 'code' => 'FEATURE_MISSING_CTA', 'severity' => 'warning',
                'message' => 'The call-to-action has no real destination.', 'remedy' => 'Set the button link of the CTA band.'],
        ],
        'newsletter_form' => [
            ['selector' => 'form[action]', 'code' => 'FEATURE_DEAD_FORM', 'severity' => 'warning',
                'message' => 'The newsletter form has no endpoint.', 'remedy' => 'Wire the form to the host newsletter endpoint (site_endpoints, ADR-066).'],
        ],
        'search_bar' => [
            ['selector' => 'form[action]', 'code' => 'FEATURE_DEAD_FORM', 'severity' => 'warning',
                'message' => 'The search form has no endpoint.', 'remedy' => 'Wire the search form to the host search route (site_endpoints, ADR-066).'],
        ],
    ];

    /**
     * @return array{schema:string, global:list<array>, profiles:array<string,list<array>>, slots:array<string,list<string>>, friction:array<string,array>, style_panel:array<string,string>}
     */
    public static function export(array $definitions): array
    {
        $manifest = WidgetSchemaContract::manifest($definitions);
        $slots = [];
        foreach ($manifest as $type => $entry) {
            if ($entry['slots'] !== []) {
                $slots[$type] = $entry['slots'];
            }
        }
        ksort($slots);
        $profiles = self::PROFILES;
        ksort($profiles);

        return [
            'schema' => self::SCHEMA,
            'global' => self::GLOBAL,
            'profiles' => $profiles,
            'slots' => $slots,
            // Owner guardrail 2026-11-02: widgets missing essential author controls.
            'friction' => WidgetFrictionProfile::analyze($definitions),
            'style_panel' => WidgetFrictionProfile::STYLE_PANEL,
        ];
    }

    /**
     * Profile problems against the registry: unknown types, unknown slots,
     * bad severities or missing texts. Empty means consistent.
     *
     * @return list<string>
     */
    public static function violations(array $definitions): array
    {
        $manifest = WidgetSchemaContract::manifest($definitions);
        $out = [];
        $check = static function (string $where, array $rule, string $selectorKey) use (&$out): void {
            foreach ([$selectorKey, 'code', 'severity', 'message', 'remedy'] as $key) {
                if (!is_string($rule[$key] ?? null) || trim($rule[$key]) === '') {
                    $out[] = "{$where}: missing {$key}";
                }
            }
            if (!in_array($rule['severity'] ?? null, self::SEVERITIES, true)) {
                $out[] = "{$where}: unknown severity";
            }
            if (isset($rule['code']) && !preg_match('/^[A-Z][A-Z0-9_]+$/', (string) $rule['code'])) {
                $out[] = "{$where}: code must be UPPER_SNAKE";
            }
        };
        foreach (self::GLOBAL as $i => $rule) {
            $check("global[{$i}]", $rule, 'forbid');
        }
        foreach (self::PROFILES as $type => $rules) {
            if (!isset($manifest[$type])) {
                $out[] = "{$type}: not a registry type";
                continue;
            }
            foreach ($rules as $i => $rule) {
                $check("{$type}[{$i}]", $rule, 'selector');
                preg_match_all('/data-widget-slot="([^"]+)"/', (string) ($rule['selector'] ?? ''), $m);
                foreach ($m[1] as $slot) {
                    if (!in_array($slot, $manifest[$type]['slots'], true)) {
                        $out[] = "{$type}[{$i}]: slot '{$slot}' is not declared by {$type}";
                    }
                }
            }
        }
        return $out;
    }
}
