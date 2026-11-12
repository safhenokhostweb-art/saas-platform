<?php

namespace Tests\Unit\ThemeBuilder;

use SaaSPlatform\ThemeRuntime\TemplateLibrary;
use SaaSPlatform\ThemeRuntime\ThemeTokenNormalizer;
use Tests\TestCase;

/**
 * PLATFORM-THEME-SPEC-v1 (ADR-083): the solar-dark preset is plain
 * global_styles, and the normalizer turns it into the spec's exact palette.
 */
class SolarDarkTokenPresetTest extends TestCase
{
    private function vars(): array
    {
        $preset = TemplateLibrary::tokenPresets()['solar-dark'] ?? null;
        $this->assertIsArray($preset, 'the solar-dark preset ships with the library');

        return (new ThemeTokenNormalizer())->normalize($preset['global_styles']);
    }

    public function test_the_preset_normalizes_to_the_spec_palette(): void
    {
        $vars = $this->vars();

        $this->assertSame('#0B0B0B', $vars['--tb-background']);
        $this->assertSame('#1E1E1E', $vars['--tb-surface']);
        $this->assertSame('#12121F', $vars['--tb-secondary']);
        $this->assertSame('#12121F', $vars['--tb-input-bg']);
        $this->assertSame('#FF7900', $vars['--tb-primary']);
        $this->assertSame('#E06600', $vars['--tb-primary-hover']);
        $this->assertSame('#2A2A2A', $vars['--tb-border']);
        $this->assertSame('6px', $vars['--tb-radius-sm']);
        $this->assertSame('10px', $vars['--tb-radius-md']);
        $this->assertStringStartsWith('IRANSans, Vazir', $vars['--tb-font-body']);
        // The platform ships Vazirmatn, so the stack always reaches a real Persian face.
        $this->assertStringContainsString('Vazirmatn', $vars['--tb-font-heading']);
    }

    public function test_text_on_the_dark_surfaces_stays_readable(): void
    {
        $vars = $this->vars();

        // Orange buttons carry near-black text, cards carry light text.
        $this->assertSame('#111827', $vars['--tb-on-primary']);
        $this->assertSame('#ffffff', $vars['--tb-surface-text']);
    }

    public function test_the_presets_only_use_tokens_the_normalizer_reads(): void
    {
        $known = array_merge(
            ['primary', 'secondary', 'accent', 'success', 'warning', 'error', 'background', 'surface', 'text', 'text_muted', 'border'],
            array_keys(ThemeTokenNormalizer::COMMERCIAL_TOKENS),
        );
        foreach (TemplateLibrary::tokenPresets() as $key => $preset) {
            $this->assertSame([], array_values(array_diff(array_keys($preset['global_styles']['colors'] ?? []), $known)), "{$key} declares colours nothing reads");
        }
    }
}
