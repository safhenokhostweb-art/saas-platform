<?php

namespace Tests\Unit\Modules\MotionGraphics\Models;

use App\Modules\MotionGraphics\Models\TemplateAssetPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TemplateAssetPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_package_with_a_generated_uuid(): void
    {
        $package = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-1',
            'required_assets' => [['asset_id' => 'motion-1', 'version' => 1]],
            'optional_assets' => [],
            'compatibility_rules' => ['industry' => 'finance'],
            'package_version' => 1,
            'created_by_actor_identifier' => 'user:1',
        ]);

        $this->assertNotEmpty($package->uuid);
        $this->assertSame('template-1', $package->template_reference);
        $this->assertSame([['asset_id' => 'motion-1', 'version' => 1]], $package->required_assets);
    }

    public function test_it_rejects_a_duplicate_template_reference_and_package_version(): void
    {
        TemplateAssetPackage::query()->create([
            'template_reference' => 'template-dup', 'required_assets' => [], 'package_version' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        TemplateAssetPackage::query()->create([
            'template_reference' => 'template-dup', 'required_assets' => [], 'package_version' => 1,
        ]);
    }

    public function test_immutable_fields_cannot_be_updated(): void
    {
        $package = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-immutable', 'required_assets' => [], 'package_version' => 1,
        ]);

        $this->expectException(LogicException::class);

        $package->update(['template_reference' => 'changed']);
    }

    public function test_the_package_cannot_be_deleted(): void
    {
        $package = TemplateAssetPackage::query()->create([
            'template_reference' => 'template-no-delete', 'required_assets' => [], 'package_version' => 1,
        ]);

        $this->expectException(LogicException::class);

        $package->delete();
    }
}
