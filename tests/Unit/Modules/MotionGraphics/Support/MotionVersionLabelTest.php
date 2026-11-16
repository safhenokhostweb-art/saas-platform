<?php

namespace Tests\Unit\Modules\MotionGraphics\Support;

use App\Modules\MotionGraphics\Services\MotionLibraryCatalogService;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphic;
use SaaSPlatform\Modules\MotionGraphics\Models\MotionGraphicVersion;
use Tests\TestCase;

/**
 * ADR-086: version labels, build ids and declared visibility, as pure rules.
 */
final class MotionVersionLabelTest extends TestCase
{
    public static function labels(): array
    {
        return [
            'library dir' => ['v2', true],
            'semver' => ['1.2.0', true],
            'two part' => ['1.2', true],
            'prerelease' => ['1.2.0-rc.1', true],
            'build metadata' => ['2.1.0+build.7', true],
            'empty' => ['', false],
            'words' => ['latest', false],
            'markup' => ['<b>1</b>', false],
            'too many parts' => ['1.2.3.4', false],
            'too long' => ['1.2.3-'.str_repeat('a', 30), false],
            'not a string' => [3, false],
        ];
    }

    #[DataProvider('labels')]
    public function test_label_validation(mixed $label, bool $valid): void
    {
        $this->assertSame($valid, MotionGraphicVersion::isValidVersionLabel($label));
    }

    public function test_the_stored_label_wins_then_the_library_directory_then_the_integer(): void
    {
        $this->assertSame('1.4.0', (new MotionGraphicVersion(['version' => 3, 'version_label' => '1.4.0', 'preview_metadata' => ['library_version' => 'v2']]))->versionLabel());
        // Rows published before ADR-086 carry no label; their library dir is the label.
        $this->assertSame('v2', (new MotionGraphicVersion(['version' => 3, 'preview_metadata' => ['library_version' => 'v2']]))->versionLabel());
        $this->assertSame('v3', (new MotionGraphicVersion(['version' => 3]))->versionLabel());
        $this->assertSame('v3', (new MotionGraphicVersion(['version' => 3, 'version_label' => 'not a label']))->versionLabel());
    }

    public function test_build_id_is_the_compiled_digest_prefix_or_null(): void
    {
        $sha = hash('sha256', 'bytes');
        $this->assertSame(substr($sha, 0, 12), (new MotionGraphicVersion(['compiled_assets' => [['sha256' => $sha]]]))->buildId());
        $this->assertNull((new MotionGraphicVersion(['compiled_assets' => [['sha256' => 'short']]]))->buildId());
        $this->assertNull((new MotionGraphicVersion(['compiled_assets' => []]))->buildId());
    }

    public function test_the_catalog_label_comes_from_metadata_then_the_library_directory(): void
    {
        $this->assertSame('1.0.0', MotionLibraryCatalogService::versionLabelFor(['version_label' => '1.0.0', 'version' => 'v1'], 'v1'));
        $this->assertSame('v2', MotionLibraryCatalogService::versionLabelFor(['version' => 'v2'], 'v2'));
        $this->assertSame('v4', MotionLibraryCatalogService::versionLabelFor(['version_label' => 'final!'], 'v4'));
    }

    public function test_declared_visibility_is_public_by_default_and_strict_otherwise(): void
    {
        $this->assertSame(MotionGraphic::VISIBILITY_PUBLIC, MotionLibraryCatalogService::declaredVisibility([]));
        $this->assertSame(MotionGraphic::VISIBILITY_PRIVATE, MotionLibraryCatalogService::declaredVisibility(['visibility' => 'private']));

        $this->expectException(RuntimeException::class);
        MotionLibraryCatalogService::declaredVisibility(['visibility' => 'hidden']);
    }

    public function test_the_library_files_declare_what_the_catalog_publishes(): void
    {
        $this->assertSame('public', MotionLibraryCatalogService::fileVisibility('hero-motion', 'v2'));
        $this->assertSame('public', MotionLibraryCatalogService::fileVisibility('business-growth-motion', 'v2'));
        $this->assertSame('private', MotionLibraryCatalogService::fileVisibility('solar-motion-graphic', 'v1'));
        $this->assertTrue(MotionLibraryCatalogService::isPrivateLibraryFile('solar-motion-graphic', 'v1'));
        $this->assertFalse(MotionLibraryCatalogService::isPrivateLibraryFile('hero-motion', 'v2'));
        $this->assertFalse(MotionLibraryCatalogService::isPrivateLibraryFile('not-in-catalog', 'v1'));
    }
}
