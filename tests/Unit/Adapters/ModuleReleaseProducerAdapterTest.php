<?php

namespace Tests\Unit\Adapters;

use App\Adapters\ModuleReleaseProducerAdapter;
use App\Models\Module;
use App\Models\ModuleRelease;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Contracts\Services\Dto\ModuleReleaseRequest;
use SaaSPlatform\CoreUtils\Contracts\Services\Dto\ModuleReleaseResult;
use Tests\TestCase;

/**
 * Commit 12-F — ModuleReleaseProducerAdapter scenarios.
 */
class ModuleReleaseProducerAdapterTest extends TestCase
{
    use RefreshDatabase;

    private ModuleReleaseProducerAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new ModuleReleaseProducerAdapter();
    }

    private function makeModule(string $version = '1.0.0'): Module
    {
        return Module::create([
            'slug' => 'test-module-' . uniqid('', true),
            'title' => 'ماژول تست',
            'description' => null,
            'is_active' => true,
            'version' => $version,
            'type' => Module::TYPE_CORE,
        ]);
    }

    public function test_existing_module_with_new_version_is_created(): void
    {
        $module = $this->makeModule('1.0.0');

        $result = $this->adapter->record(new ModuleReleaseRequest(
            slug: $module->slug,
            version: '1.1.0',
            releaseType: 'minor',
            changelog: 'تست تغییرات',
        ));

        $this->assertSame(ModuleReleaseResult::STATUS_CREATED, $result->status);
        $this->assertNotNull($result->releaseId);

        $this->assertDatabaseHas('module_releases', [
            'module_id' => $module->id,
            'version' => '1.1.0',
            'is_active' => true,
        ]);

        $this->assertSame('1.1.0', $module->fresh()->version);
    }

    public function test_module_not_found_is_skipped(): void
    {
        $result = $this->adapter->record(new ModuleReleaseRequest(
            slug: 'non-existent-module-' . uniqid('', true),
            version: '1.0.0',
            releaseType: 'patch',
        ));

        $this->assertSame(ModuleReleaseResult::STATUS_SKIPPED_NO_MODULE_ROW, $result->status);
        $this->assertNotNull($result->reason);
    }

    public function test_duplicate_active_version_is_skipped(): void
    {
        $module = $this->makeModule('1.0.0');

        ModuleRelease::create([
            'module_id' => $module->id,
            'version' => '1.0.0',
            'release_type' => 'patch',
            'is_active' => true,
        ]);

        $result = $this->adapter->record(new ModuleReleaseRequest(
            slug: $module->slug,
            version: '1.0.0',
            releaseType: 'patch',
        ));

        $this->assertSame(ModuleReleaseResult::STATUS_SKIPPED_DUPLICATE, $result->status);
        $this->assertNotNull($result->reason);
    }

    /**
     * Phase 17-B — Part 4: an internal (platform-only) module must never
     * receive a customer-shippable release, regardless of whether a
     * duplicate/version check would otherwise have allowed it.
     */
    public function test_internal_module_release_is_skipped(): void
    {
        $module = Module::create([
            'slug' => 'test-internal-module-' . uniqid('', true),
            'title' => 'ماژول داخلی تست',
            'is_active' => true,
            'version' => '1.0.0',
            'type' => Module::TYPE_INTERNAL,
        ]);

        $result = $this->adapter->record(new ModuleReleaseRequest(
            slug: $module->slug,
            version: '1.1.0',
            releaseType: 'minor',
        ));

        $this->assertSame(ModuleReleaseResult::STATUS_SKIPPED_INTERNAL_MODULE, $result->status);
        $this->assertNotNull($result->reason);
        $this->assertDatabaseMissing('module_releases', [
            'module_id' => $module->id,
            'version' => '1.1.0',
        ]);
        // version cache on the Module row itself must not move either.
        $this->assertSame('1.0.0', $module->fresh()->version);
    }

    public function test_database_transaction_failure_returns_failed(): void
    {
        $module = $this->makeModule('1.0.0');

        // Pre-insert an INACTIVE release row for this version. The app-level
        // duplicate check only looks at active rows, so record() proceeds
        // to the transaction — where the module_releases (module_id, version)
        // unique constraint rejects the insert, giving a real DB failure.
        DB::table('module_releases')->insert([
            'module_id' => $module->id,
            'version' => '1.1.0',
            'release_type' => 'patch',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->adapter->record(new ModuleReleaseRequest(
            slug: $module->slug,
            version: '1.1.0',
            releaseType: 'patch',
        ));

        $this->assertSame(ModuleReleaseResult::STATUS_FAILED, $result->status);
        $this->assertNotNull($result->reason);
    }
}
