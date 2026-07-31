<?php

namespace Tests\Unit\Modules\MotionGraphics\Models;

use App\Modules\MotionGraphics\Models\AssetActivation;
use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\SiteAssetPackageItem;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

class AssetActivationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;

    private function item(): SiteAssetPackageItem
    {
        $site = $this->createSite();
        $templatePackage = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1', 'required_assets' => [], 'package_version' => 1,
        ]);
        $sitePackage = SiteAssetPackage::query()->create([
            'site_id' => $site->id, 'template_reference' => $templatePackage->template_reference,
            'template_asset_package_id' => $templatePackage->id,
        ]);

        return SiteAssetPackageItem::query()->create([
            'site_asset_package_id' => $sitePackage->id, 'asset_id' => 'motion-1',
            'active_version' => 1, 'bound_checksum' => 'x', 'installed_at' => now(),
        ]);
    }

    public function test_it_records_an_install_activation(): void
    {
        $activation = AssetActivation::query()->create([
            'site_asset_package_item_id' => $this->item()->id,
            'asset_id' => 'motion-1', 'version' => 1, 'previous_version' => null,
            'activation_type' => AssetActivation::TYPE_INSTALL, 'activated_at' => now(),
        ]);

        $this->assertNotEmpty($activation->uuid);
        $this->assertNull($activation->previous_version);
    }

    public function test_history_is_append_only_and_cannot_be_updated(): void
    {
        $activation = AssetActivation::query()->create([
            'site_asset_package_item_id' => $this->item()->id,
            'asset_id' => 'motion-1', 'version' => 1, 'activation_type' => AssetActivation::TYPE_INSTALL, 'activated_at' => now(),
        ]);

        $this->expectException(LogicException::class);

        $activation->update(['version' => 2]);
    }

    public function test_history_cannot_be_deleted(): void
    {
        $activation = AssetActivation::query()->create([
            'site_asset_package_item_id' => $this->item()->id,
            'asset_id' => 'motion-1', 'version' => 1, 'activation_type' => AssetActivation::TYPE_INSTALL, 'activated_at' => now(),
        ]);

        $this->expectException(LogicException::class);

        $activation->delete();
    }

    public function test_a_rollback_row_records_the_previous_version(): void
    {
        $item = $this->item();
        $activation = AssetActivation::query()->create([
            'site_asset_package_item_id' => $item->id, 'asset_id' => 'motion-1',
            'version' => 2, 'previous_version' => 3, 'activation_type' => AssetActivation::TYPE_ROLLBACK, 'activated_at' => now(),
        ]);

        $this->assertSame(2, $activation->version);
        $this->assertSame(3, $activation->previous_version);
        $this->assertSame(AssetActivation::TYPE_ROLLBACK, $activation->activation_type);
    }
}
