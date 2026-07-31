<?php

namespace Tests\Unit\Modules\MotionGraphics\Models;

use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

class SiteAssetPackageItemTest extends TestCase
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

    public function test_active_version_and_checksum_are_mutable_the_current_pointer(): void
    {
        $item = SiteAssetPackageItem::query()->create([
            'site_asset_package_id' => $this->sitePackage()->id,
            'asset_id' => 'motion-1',
            'active_version' => 1,
            'bound_checksum' => 'checksum-v1',
            'installed_at' => now(),
        ]);

        $item->update(['active_version' => 2, 'bound_checksum' => 'checksum-v2']);

        $this->assertSame(2, $item->fresh()->active_version);
        $this->assertSame('checksum-v2', $item->fresh()->bound_checksum);
    }

    public function test_identity_fields_are_immutable(): void
    {
        $item = SiteAssetPackageItem::query()->create([
            'site_asset_package_id' => $this->sitePackage()->id,
            'asset_id' => 'motion-1', 'active_version' => 1, 'bound_checksum' => 'x', 'installed_at' => now(),
        ]);

        $this->expectException(LogicException::class);

        $item->update(['asset_id' => 'motion-2']);
    }

    public function test_one_item_per_asset_per_package(): void
    {
        $sitePackage = $this->sitePackage();
        SiteAssetPackageItem::query()->create([
            'site_asset_package_id' => $sitePackage->id, 'asset_id' => 'motion-1',
            'active_version' => 1, 'bound_checksum' => 'x', 'installed_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        SiteAssetPackageItem::query()->create([
            'site_asset_package_id' => $sitePackage->id, 'asset_id' => 'motion-1',
            'active_version' => 2, 'bound_checksum' => 'y', 'installed_at' => now(),
        ]);
    }
}
