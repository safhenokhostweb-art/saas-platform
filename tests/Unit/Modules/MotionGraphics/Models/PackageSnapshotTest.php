<?php

namespace Tests\Unit\Modules\MotionGraphics\Models;

use App\Modules\MotionGraphics\Models\PackageSnapshot;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

class PackageSnapshotTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;

    private function sitePackage(): SiteAssetPackage
    {
        $site = $this->createSite();
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1', 'required_assets' => [], 'package_version' => 1,
        ]);

        return SiteAssetPackage::query()->create([
            'site_id' => $site->id, 'template_reference' => $templatePackage->template_reference,
            'template_asset_package_id' => $templatePackage->id,
        ]);
    }

    public function test_it_stores_an_immutable_snapshot(): void
    {
        $snapshotData = ['template_reference' => 'template-1', 'assets' => [['asset_id' => 'motion-1', 'version' => 1]]];
        $snapshot = PackageSnapshot::query()->create([
            'site_asset_package_id' => $this->sitePackage()->id,
            'snapshot' => $snapshotData,
            'checksum' => hash('sha256', json_encode($snapshotData, JSON_THROW_ON_ERROR)),
            'captured_at' => now(),
        ]);

        $this->assertSame($snapshotData, $snapshot->snapshot);
        $this->assertNotEmpty($snapshot->uuid);
    }

    public function test_it_cannot_be_updated(): void
    {
        $snapshot = PackageSnapshot::query()->create([
            'site_asset_package_id' => $this->sitePackage()->id,
            'snapshot' => ['a' => 1], 'checksum' => 'x', 'captured_at' => now(),
        ]);

        $this->expectException(LogicException::class);

        $snapshot->update(['checksum' => 'tampered']);
    }

    public function test_it_cannot_be_deleted(): void
    {
        $snapshot = PackageSnapshot::query()->create([
            'site_asset_package_id' => $this->sitePackage()->id,
            'snapshot' => ['a' => 1], 'checksum' => 'x', 'captured_at' => now(),
        ]);

        $this->expectException(LogicException::class);

        $snapshot->delete();
    }
}
