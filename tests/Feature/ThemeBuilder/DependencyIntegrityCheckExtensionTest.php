<?php

namespace Tests\Feature\ThemeBuilder;

use App\Models\Extension;
use App\Models\ExtensionRelease;
use App\Models\Theme;
use App\Services\ThemeBuilder\Extension\ExtensionReleaseService;
use App\Services\ThemeBuilder\QualityGate\Checks\DependencyIntegrityCheck;
use App\Services\ThemeBuilder\QualityGate\ThemeCommercialQualityGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 3G-B-5 — DependencyIntegrityCheck's `extension` verification branch,
 * isolated exactly the way ThemeCommercialQualityGateTest already isolates
 * `module` (a fresh gate carrying only DependencyIntegrityCheck). Proves
 * ADR-047's exact-version policy is actually enforced at gate time, and that
 * historical (superseded/deprecated) releases still satisfy a dependency —
 * never gated on Extension.current_release_id.
 */
class DependencyIntegrityCheckExtensionTest extends TestCase
{
    use RefreshDatabase;

    private ExtensionReleaseService $extensionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extensionService = $this->app->make(ExtensionReleaseService::class);
    }

    private function makeGoodTheme(array $overrides = []): Theme
    {
        return Theme::create(array_merge([
            'name' => 'قالب تست',
            'slug' => 'ext-gate-theme-' . uniqid(),
            'type' => Theme::TYPE_FULL,
            'source' => Theme::SOURCE_SEEDER,
            'status' => Theme::STATUS_DRAFT,
            'category' => Theme::CATEGORY_MEDICAL,
            'description' => 'یک قالب برای تست.',
            'header_blocks' => [],
            'footer_blocks' => [],
            'pages' => ['home' => ['title' => 'خانه', 'slug' => '', 'blocks' => [['type' => 'hero']]]],
            'global_styles' => [],
            'settings' => [],
        ], $overrides));
    }

    private function gate(): ThemeCommercialQualityGate
    {
        return new ThemeCommercialQualityGate([new DependencyIntegrityCheck()]);
    }

    private function dep(string $ref, ?string $constraint, bool $required = true): array
    {
        return ['type' => 'extension', 'ref' => $ref, 'version_constraint' => $constraint, 'is_required' => $required];
    }

    public function test_a_valid_exact_extension_dependency_passes(): void
    {
        $extension = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->extensionService->release($extension, '2.3.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'L', 'category' => 'general']],
        ]);

        $report = $this->gate()->evaluate($this->makeGoodTheme(), '1.0.0', [$this->dep('acme/gallery-pro', '=2.3.0')]);

        $this->assertTrue($report->isPassing());
        $codes = array_column($report->checks, 'code');
        $this->assertContains('dependency.extension_resolved', $codes);
    }

    public function test_missing_extension_product_fails(): void
    {
        $report = $this->gate()->evaluate($this->makeGoodTheme(), '1.0.0', [$this->dep('acme/does-not-exist', '=1.0.0')]);

        $this->assertFalse($report->isPassing());
        $this->assertContains('dependency.required_extension_missing', array_column($report->blockingFailures(), 'code'));
    }

    public function test_missing_exact_release_fails(): void
    {
        $this->extensionService->release(
            $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro'),
            '1.0.0',
            1,
            ['elements' => [['type' => 'ext:acme:gallery', 'label' => 'L', 'category' => 'general']]]
        );

        $report = $this->gate()->evaluate($this->makeGoodTheme(), '1.0.0', [$this->dep('acme/gallery-pro', '=9.9.9')]);

        $this->assertFalse($report->isPassing());
        $this->assertContains('dependency.required_extension_release_missing', array_column($report->blockingFailures(), 'code'));
    }

    public function test_invalid_manifest_fails(): void
    {
        $extension = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        DB::table('extension_releases')->insert([
            'extension_id' => $extension->id,
            'version' => '1.0.0',
            'sdk_contract_version' => 1,
            'manifest' => json_encode(['not' => 'a valid manifest']),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = $this->gate()->evaluate($this->makeGoodTheme(), '1.0.0', [$this->dep('acme/gallery-pro', '=1.0.0')]);

        $this->assertFalse($report->isPassing());
        $this->assertContains('dependency.extension_invalid_manifest', array_column($report->blockingFailures(), 'code'));
    }

    /** @dataProvider nonExactConstraintProvider */
    public function test_non_exact_constraints_fail_closed(?string $constraint): void
    {
        $this->extensionService->release(
            $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro'),
            '2.3.0',
            1,
            ['elements' => [['type' => 'ext:acme:gallery', 'label' => 'L', 'category' => 'general']]]
        );

        $report = $this->gate()->evaluate($this->makeGoodTheme(), '1.0.0', [$this->dep('acme/gallery-pro', $constraint)]);

        $this->assertFalse($report->isPassing(), "expected constraint " . var_export($constraint, true) . ' to fail closed');
        $this->assertContains('dependency.extension_non_exact_constraint', array_column($report->blockingFailures(), 'code'));
    }

    public static function nonExactConstraintProvider(): array
    {
        return [
            'null' => [null],
            'wildcard' => ['*'],
            'latest' => ['latest'],
            'current' => ['current'],
            'gte' => ['>=2.3.0'],
            'caret' => ['^2.3.0'],
        ];
    }

    public function test_superseded_exact_historical_release_passes(): void
    {
        $extension = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->extensionService->release($extension, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'L', 'category' => 'general']],
        ]);
        // 2.0.0 supersedes 1.0.0.
        $this->extensionService->release($extension->fresh(), '2.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'L', 'category' => 'general']],
        ]);

        $report = $this->gate()->evaluate($this->makeGoodTheme(), '1.0.0', [$this->dep('acme/gallery-pro', '=1.0.0')]);

        $this->assertTrue($report->isPassing(), 'a Theme pinned to a superseded-but-valid release must still pass');
    }

    public function test_deprecated_exact_historical_release_passes(): void
    {
        $extension = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $release = $this->extensionService->release($extension, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'L', 'category' => 'general']],
        ]);
        $release->update(['status' => ExtensionRelease::STATUS_DEPRECATED]);

        $report = $this->gate()->evaluate($this->makeGoodTheme(), '1.0.0', [$this->dep('acme/gallery-pro', '=1.0.0')]);

        $this->assertTrue($report->isPassing(), 'a Theme pinned to a deprecated-but-valid release must still pass');
    }

    public function test_dependency_resolution_does_not_require_current_release_id(): void
    {
        $extension = $this->extensionService->createExtension('acme', 'gallery-pro', 'Gallery Pro');
        $this->extensionService->release($extension, '1.0.0', 1, [
            'elements' => [['type' => 'ext:acme:gallery', 'label' => 'L', 'category' => 'general']],
        ]);
        // current_release_id moves to 2.0.0, whose manifest differs entirely.
        $this->extensionService->release($extension->fresh(), '2.0.0', 1, [
            'elements' => [['type' => 'ext:acme:other', 'label' => 'L', 'category' => 'general']],
        ]);
        $extension->refresh();
        $this->assertSame('2.0.0', $extension->currentRelease->version);

        // The Theme still declares =1.0.0 — must resolve against THAT release, not current.
        $report = $this->gate()->evaluate($this->makeGoodTheme(), '1.0.0', [$this->dep('acme/gallery-pro', '=1.0.0')]);

        $this->assertTrue($report->isPassing());
    }

    public function test_optional_missing_extension_is_a_warning_not_a_block(): void
    {
        $report = $this->gate()->evaluate($this->makeGoodTheme(), '1.0.0', [$this->dep('acme/does-not-exist', '=1.0.0', false)]);

        $this->assertTrue($report->isPassing());
        $this->assertContains('dependency.optional_extension_missing', array_column($report->nonBlockingFindings(), 'code'));
        $this->assertNotContains('dependency.required_extension_missing', array_column($report->checks, 'code'));
    }

    public function test_malformed_product_identity_fails_closed(): void
    {
        $report = $this->gate()->evaluate($this->makeGoodTheme(), '1.0.0', [$this->dep('not-a-valid-identity', '=1.0.0')]);

        $this->assertFalse($report->isPassing());
        $this->assertContains('dependency.extension_malformed_identity', array_column($report->blockingFailures(), 'code'));
    }
}
