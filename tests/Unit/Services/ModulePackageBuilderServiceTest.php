<?php

namespace Tests\Unit\Services;

use App\Models\Module;
use App\Models\ModuleRelease;
use App\Services\ModulePackageBuilderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 13-H — ModulePackageBuilderService scenarios.
 *
 * Uses the real "forms" package on disk (packages/saas-platform/modules/forms)
 * since the builder zips real source files — there is no fake/in-memory seam
 * for ModuleVersionRegistry::path().
 */
class ModulePackageBuilderServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SLUG = 'forms';

    /**
     * Phase 17-F — Part 3: ModulePackageValidator now cross-checks the
     * package's real, on-disk module.json version against the
     * module_releases row it's being attached to — a mismatch is a hard
     * failure. Tests that actually build the real forms ZIP must therefore
     * register the release under forms/module.json's real version, not an
     * arbitrary placeholder like the old "9.9.9". Read dynamically so this
     * never drifts if forms/module.json's version is bumped later.
     */
    private function realFormsVersion(): string
    {
        $manifest = json_decode(
            file_get_contents(base_path('packages/saas-platform/modules/forms/module.json')),
            true
        );

        return $manifest['version'];
    }

    protected function tearDown(): void
    {
        $zip = storage_path('app/modules/packages/' . self::SLUG . '-' . $this->realFormsVersion() . '.zip');
        @unlink($zip);
        parent::tearDown();
    }

    private function makeModule(): Module
    {
        return Module::updateOrCreate(
            ['slug' => self::SLUG],
            ['title' => 'ماژول فرم', 'is_active' => true, 'version' => '1.0.0', 'type' => Module::TYPE_CORE]
        );
    }

    public function test_build_registers_artifact_on_existing_release(): void
    {
        $version = $this->realFormsVersion();
        $module = $this->makeModule();
        $release = ModuleRelease::create([
            'module_id' => $module->id,
            'version' => $version,
            'release_type' => 'patch',
            'is_active' => true,
        ]);

        $result = app(ModulePackageBuilderService::class)->build(self::SLUG, $version);

        $this->assertSame($release->id, $result['module_release_id']);
        $this->assertFileExists($result['package_path']);
        $this->assertSame(64, strlen($result['package_sha256']));
        $this->assertGreaterThan(0, $result['package_size_bytes']);

        $this->assertSame(hash_file('sha256', $result['package_path']), $result['package_sha256']);

        $release->refresh();
        $this->assertSame($result['package_sha256'], $release->package_sha256);
        $this->assertSame($result['package_path'], $release->package_path);
        $this->assertSame($result['package_size_bytes'], $release->package_size_bytes);
        $this->assertNull($release->package_key);
    }

    /**
     * Phase 17-B — Part 4: independent guard from
     * ModuleReleaseProducerAdapter's — fires even if a release row already
     * exists (e.g. one created before this phase), since this is the point
     * that actually produces a shippable ZIP artifact.
     */
    public function test_build_fails_for_an_internal_module_even_with_an_existing_release_row(): void
    {
        $module = Module::create([
            'slug' => 'test-internal-' . uniqid('', true),
            'title' => 'ماژول داخلی تست',
            'is_active' => true,
            'version' => '1.0.0',
            'type' => Module::TYPE_INTERNAL,
        ]);
        ModuleRelease::create([
            'module_id' => $module->id,
            'version' => '9.9.9',
            'release_type' => 'patch',
            'is_active' => true,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/type=internal/');

        app(ModulePackageBuilderService::class)->build($module->slug, '9.9.9');
    }

    public function test_build_fails_without_existing_release_row(): void
    {
        $this->makeModule();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/No module_releases row/');

        app(ModulePackageBuilderService::class)->build(self::SLUG, '9.9.9');
    }

    public function test_build_fails_when_already_registered_without_force(): void
    {
        $module = $this->makeModule();
        ModuleRelease::create([
            'module_id' => $module->id,
            'version' => '9.9.9',
            'release_type' => 'patch',
            'is_active' => true,
            'package_sha256' => str_repeat('a', 64),
            'package_path' => '/tmp/already-there.zip',
            'package_size_bytes' => 123,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/already has a registered package/');

        app(ModulePackageBuilderService::class)->build(self::SLUG, '9.9.9');
    }

    public function test_build_with_force_overwrites_existing_registration(): void
    {
        $version = $this->realFormsVersion();
        $module = $this->makeModule();
        $release = ModuleRelease::create([
            'module_id' => $module->id,
            'version' => $version,
            'release_type' => 'patch',
            'is_active' => true,
            'package_sha256' => str_repeat('a', 64),
            'package_path' => '/tmp/already-there.zip',
            'package_size_bytes' => 123,
        ]);

        $result = app(ModulePackageBuilderService::class)->build(self::SLUG, $version, force: true);

        $release->refresh();
        $this->assertNotSame(str_repeat('a', 64), $release->package_sha256);
        $this->assertSame($result['package_sha256'], $release->package_sha256);
    }
}
