<?php

namespace Tests\Unit\ThemeBuilder;

use App\Console\Commands\SentinelProfilesCommand;
use SaaSPlatform\ThemeRuntime\BlockRegistry;
use SaaSPlatform\ThemeRuntime\Widget\WidgetFeatureProfile;
use Tests\TestCase;

/**
 * ADR-077: the Sentinel UI verifier's widget profiles name only real
 * registry types and declared slots, and the committed
 * tests/sentinel/widget-profiles.json is what `sentinel:profiles` writes.
 */
class Adr077SentinelProfilesTest extends TestCase
{
    public function test_profiles_name_only_registry_types_and_declared_slots(): void
    {
        $this->assertSame([], WidgetFeatureProfile::violations(BlockRegistry::all()));
    }

    public function test_violations_catch_unknown_types_slots_and_severities(): void
    {
        $definitions = ['w' => ['name' => 'W', 'category' => 'content', 'icon' => 'box', 'schema' => [], 'defaultProps' => [], 'slots' => ['body']]];
        $this->assertSame([], WidgetFeatureProfile::violations($definitions + BlockRegistry::all()));
        $this->assertContains('nav_header: not a registry type', WidgetFeatureProfile::violations($definitions));
    }

    public function test_the_committed_profiles_file_is_current(): void
    {
        $this->assertFileExists(SentinelProfilesCommand::path());
        $this->assertSame(SentinelProfilesCommand::render(), file_get_contents(SentinelProfilesCommand::path()), 'run php artisan sentinel:profiles');
        $this->artisan('sentinel:profiles', ['--check' => true])->assertExitCode(0);
    }

    public function test_the_export_carries_header_and_footer_requirements(): void
    {
        $export = WidgetFeatureProfile::export(BlockRegistry::all());
        $this->assertSame(WidgetFeatureProfile::SCHEMA, $export['schema']);
        $this->assertSame(['logo', 'menu', 'actions', 'quick-access'], $export['slots']['nav_header']);
        $codes = array_column($export['profiles']['nav_header'], 'code');
        $this->assertSame(['FEATURE_MISSING_MENU', 'FEATURE_EMPTY_MENU', 'FEATURE_MISSING_LOGO', 'FEATURE_MISSING_ACTIONS'], $codes);
        $this->assertSame('DEAD_LINK', $export['global'][0]['code']);
        foreach ($export['profiles'] as $type => $rules) {
            foreach ($rules as $rule) {
                $this->assertNotSame('', trim($rule['remedy']), "{$type}/{$rule['code']} has a task text");
            }
        }
    }

    public function test_the_suite_is_read_only_and_driven_by_base_url(): void
    {
        $config = file_get_contents(base_path('tests/sentinel/playwright.sentinel.config.ts'));
        $env = file_get_contents(base_path('tests/sentinel/support/env.ts'));
        $this->assertStringContainsString("updateSnapshots: env.updateBaselines ? 'all' : 'none'", $config);
        $this->assertStringContainsString('process.env.BASE_URL', $env);
        foreach (glob(base_path('tests/sentinel/*.sentinel.ts')) as $spec) {
            $code = file_get_contents($spec);
            // `.type(` with an argument types into a field; `message.type()` only reads.
            $this->assertDoesNotMatchRegularExpression('/\.(click|dblclick|fill|press|check|uncheck|selectOption|setInputFiles|dragTo)\(|\.type\(\s*[^)\s]|method:\s*[\'"](POST|PUT|PATCH|DELETE)/i', $code, basename($spec) . ' must only read');
        }
    }
}
