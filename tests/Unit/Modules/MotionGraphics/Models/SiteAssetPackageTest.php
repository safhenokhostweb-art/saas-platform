<?php

namespace Tests\Unit\Modules\MotionGraphics\Models;

use App\Modules\MotionGraphics\Models\SiteAssetPackage;
use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Support\CreatesMotionTestSite;
use Tests\TestCase;

class SiteAssetPackageTest extends TestCase
{
    use RefreshDatabase;
    use CreatesMotionTestSite;

    private function templatePackage(string $templateReference = 'template-1', int $version = 1): TemplateAssetPackage
    {
        return TemplateAssetPackage::query()->create([
            'template_reference' => $templateReference, 'required_assets' => [], 'package_version' => $version,
        ]);
    }

    public function test_it_defaults_to_pending_status_on_creation(): void
    {
        $site = $this->createSite();
        $templatePackage = $this->templatePackage();
        $package = SiteAssetPackage::query()->create([
            'site_id' => $site->id,
            'template_reference' => $templatePackage->template_reference,
            'template_asset_package_id' => $templatePackage->id,
        ]);

        $this->assertSame(SiteAssetPackage::STATUS_PENDING, $package->activation_status);
        $this->assertNotEmpty($package->uuid);
    }

    public function test_activation_status_and_activated_at_are_mutable(): void
    {
        $site = $this->createSite();
        $templatePackage = $this->templatePackage();
        $package = SiteAssetPackage::query()->create([
            'site_id' => $site->id, 'template_reference' => $templatePackage->template_reference,
            'template_asset_package_id' => $templatePackage->id,
        ]);

        $package->update(['activation_status' => SiteAssetPackage::STATUS_ACTIVE, 'activated_at' => now()]);

        $this->assertSame(SiteAssetPackage::STATUS_ACTIVE, $package->fresh()->activation_status);
    }

    public function test_template_asset_package_id_is_the_mutable_current_pointer(): void
    {
        $site = $this->createSite();
        $templatePackageV1 = $this->templatePackage('template-1', 1);
        $templatePackageV2 = $this->templatePackage('template-1', 2);
        $package = SiteAssetPackage::query()->create([
            'site_id' => $site->id, 'template_reference' => $templatePackageV1->template_reference,
            'template_asset_package_id' => $templatePackageV1->id,
        ]);

        $package->update(['template_asset_package_id' => $templatePackageV2->id]);

        $this->assertSame($templatePackageV2->id, $package->fresh()->template_asset_package_id);
    }

    public function test_identity_fields_are_immutable(): void
    {
        $site = $this->createSite();
        $otherSite = $this->createSite('Other Site');
        $templatePackage = $this->templatePackage();
        $package = SiteAssetPackage::query()->create([
            'site_id' => $site->id, 'template_reference' => $templatePackage->template_reference,
            'template_asset_package_id' => $templatePackage->id,
        ]);

        $this->expectException(LogicException::class);

        $package->update(['site_id' => $otherSite->id]);
    }

    public function test_a_site_cannot_have_two_packages_for_the_same_template_reference(): void
    {
        $site = $this->createSite();
        $templatePackage = $this->templatePackage();
        SiteAssetPackage::query()->create([
            'site_id' => $site->id, 'template_reference' => $templatePackage->template_reference,
            'template_asset_package_id' => $templatePackage->id,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        SiteAssetPackage::query()->create([
            'site_id' => $site->id, 'template_reference' => $templatePackage->template_reference,
            'template_asset_package_id' => $templatePackage->id,
        ]);
    }
}
