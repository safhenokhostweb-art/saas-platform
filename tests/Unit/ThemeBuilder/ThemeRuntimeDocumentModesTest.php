<?php

namespace Tests\Unit\ThemeBuilder;

use App\Services\Seo\SeoService;
use App\Services\ThemeCanvas\ThemeRuntimeDocument;
use Mockery;
use SaaSPlatform\CoreUtils\SEO\MetaData;
use Tests\TestCase;

/**
 * Smart Public Rendering — the ThemeRuntimeDocument dual-mode contract:
 * mode validation, mode-filtered requiredAssets(), and the runtime-only
 * capabilityProjection.
 */
class ThemeRuntimeDocumentModesTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function document(string $mode): ThemeRuntimeDocument
    {
        $seo = Mockery::mock(SeoService::class);
        $seo->shouldIgnoreMissing();

        return new ThemeRuntimeDocument($seo, $mode);
    }

    public function test_default_mode_is_editor(): void
    {
        $seo = Mockery::mock(SeoService::class);
        $seo->shouldIgnoreMissing();

        $this->assertTrue((new ThemeRuntimeDocument($seo))->isEditor());
        $this->assertFalse((new ThemeRuntimeDocument($seo))->isPublic());
    }

    public function test_unknown_mode_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->document('preview');
    }

    public function test_public_mode_requires_exactly_identity_and_interaction_runtime(): void
    {
        $assets = $this->document(ThemeRuntimeDocument::MODE_PUBLIC)->requiredAssets();

        $this->assertSame(
            ['theme-runtime::canvas.identity', 'theme-runtime::interaction.runtime'],
            $assets
        );
        $this->assertNotContains('theme-runtime::canvas.editor-bootstrap', $assets);
    }

    public function test_editor_mode_requires_the_editor_bridge(): void
    {
        $assets = $this->document(ThemeRuntimeDocument::MODE_EDITOR)->requiredAssets();

        $this->assertSame(['theme-runtime::canvas.editor-bootstrap'], $assets);
    }

    public function test_public_capability_projection_keeps_runtime_capabilities_only(): void
    {
        $projected = $this->document(ThemeRuntimeDocument::MODE_PUBLIC)->capabilityProjection([
            'runtime.interactions' => ['scroll_to', 'open_modal'],
            'runtime.identity.version' => 1,
            'canEdit' => true,
            'canMove' => true,
            'canDelete' => true,
            'allowedChildKinds' => ['section', 'widget'],
        ]);

        $this->assertSame(
            ['runtime.interactions' => ['scroll_to', 'open_modal'], 'runtime.identity.version' => 1],
            $projected
        );
    }

    public function test_bare_runtime_capability_key_is_kept(): void
    {
        $projected = $this->document(ThemeRuntimeDocument::MODE_PUBLIC)->capabilityProjection([
            'runtime' => ['interactions' => true],
            'canEdit' => true,
        ]);

        $this->assertSame(['runtime' => ['interactions' => true]], $projected);
    }

    public function test_editor_mode_projection_is_unfiltered(): void
    {
        $capabilities = ['canEdit' => true, 'canMove' => true, 'runtime.interactions' => []];

        $this->assertSame(
            $capabilities,
            $this->document(ThemeRuntimeDocument::MODE_EDITOR)->capabilityProjection($capabilities)
        );
    }

    public function test_resolve_metadata_uses_canvas_public_seo_context(): void
    {
        $expected = MetaData::default();

        $seo = Mockery::mock(SeoService::class);
        $seo->shouldReceive('resolveForCanvasPublic')
            ->once()
            ->with(Mockery::on(fn (array $source): bool =>
                ($source['title'] ?? null) === 'صفحه عمومی'
                && array_key_exists('seo', $source)
                && array_key_exists('theme_settings', $source)
                && array_key_exists('global_styles', $source)
            ))
            ->andReturn($expected);

        $document = new ThemeRuntimeDocument($seo); // editor default — SEO mode-independent

        $this->assertSame($expected, $document->resolveMetaData(['title' => 'صفحه عمومی']));
    }
}
