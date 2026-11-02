<?php

namespace SaaSPlatform\ThemeRuntime\Widget;

/**
 * ADR-077 (owner guardrail 2026-11-02): the widget friction metric. A
 * widget whose kind needs an essential author control that its schema does
 * not declare is an ergonomics (UX friction) task for the Sentinel UI
 * verifier, at the lowest priority (P3).
 *
 * Read from the schema only. Controls that both editors' Style tab gives
 * every block (per-breakpoint padding/margin and corner radius) are
 * recorded as provided, never as missing: a per-widget task for them would
 * be false.
 */
final class WidgetFrictionProfile
{
    /** Style-tab controls every block has in both Canvas editors. */
    public const STYLE_PANEL = [
        'responsive_padding' => 'پدینگ مستقل موبایل/تبلت/دسکتاپ (تب استایل، هر بریک‌پوینت)',
        'radius' => 'شعاع گوشه (تب استایل)',
    ];

    /**
     * kind => [control => [label, candidate schema keys]]. A control counts
     * as present when the schema declares any candidate key.
     */
    public const KINDS = [
        'carousel' => [
            'drag' => ['فیزیک درگ و سوایپ', ['drag', 'draggable', 'swipe', 'drag_physics', 'free_mode']],
            'dots' => ['دات‌های ناوبری', ['dots', 'show_dots', 'pagination', 'show_pagination']],
            'arrows' => ['فلش‌های ناوبری', ['arrows', 'show_arrows', 'navigation', 'show_controls']],
            'autoplay' => ['پخش خودکار', ['autoplay']],
        ],
    ];

    /** The widget kind, or null when no friction rule applies. */
    public static function kind(string $type, array $definition): ?string
    {
        if (in_array($definition['category'] ?? null, WidgetSchemaContract::THEME_PACK_CATEGORIES, true)) {
            return null; // locked reference-theme blocks are not authored control by control
        }
        $schema = is_array($definition['schema'] ?? null) ? $definition['schema'] : [];
        if (preg_match('/(^|_)(slider|carousel)(_|$)/', $type) === 1 || (isset($schema['autoplay']) && isset($schema['interval']))) {
            return 'carousel';
        }
        return null;
    }

    /**
     * type => {kind, missing: [{control, label}], provided_by_style_panel}
     * for every widget with at least one missing control.
     *
     * @return array<string, array{kind:string, missing:list<array{control:string,label:string}>, provided_by_style_panel:list<string>}>
     */
    public static function analyze(array $definitions): array
    {
        $out = [];
        foreach ($definitions as $type => $definition) {
            if (!is_array($definition) || ($kind = self::kind((string) $type, $definition)) === null) {
                continue;
            }
            $schema = is_array($definition['schema'] ?? null) ? $definition['schema'] : [];
            $missing = [];
            foreach (self::KINDS[$kind] as $control => [$label, $candidates]) {
                if (array_intersect($candidates, array_keys($schema)) === []) {
                    $missing[] = ['control' => $control, 'label' => $label];
                }
            }
            if ($missing !== []) {
                $out[(string) $type] = ['kind' => $kind, 'missing' => $missing, 'provided_by_style_panel' => array_keys(self::STYLE_PANEL)];
            }
        }
        ksort($out);
        return $out;
    }
}
