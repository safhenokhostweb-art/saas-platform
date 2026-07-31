<?php

namespace Tests\Unit\Modules\MotionGraphics\DTO;

use App\Modules\MotionGraphics\DTO\DeploymentManifest;
use App\Modules\MotionGraphics\DTO\MotionAssetReferenceDTO;
use App\Modules\MotionGraphics\DTO\RuntimeProjectionDTO;
use App\Modules\MotionGraphics\DTO\ValidationReport;
use Tests\TestCase;

class DeploymentManifestTest extends TestCase
{
    private function manifest(string $status = DeploymentManifest::STATUS_PREPARED): DeploymentManifest
    {
        $asset = new MotionAssetReferenceDTO('motion-1', 1, 'lottie');

        return new DeploymentManifest(
            uuid: 'manifest-1',
            siteReference: 'site-1',
            templateReference: 'template-1',
            packageVersion: 1,
            assetReferences: [$asset],
            runtimeProjectionReferences: [new RuntimeProjectionDTO($asset, 1, [], [], '/motion/x/v1/a.json')],
            checksum: 'abc123',
            compatibilityResult: ValidationReport::pass(),
            status: $status,
        );
    }

    public function test_it_defaults_to_the_prepared_status(): void
    {
        $manifest = $this->manifest();

        $this->assertSame(DeploymentManifest::STATUS_PREPARED, $manifest->status);
    }

    public function test_with_status_returns_a_new_instance_with_the_same_content(): void
    {
        $manifest = $this->manifest();

        $validated = $manifest->withStatus(DeploymentManifest::STATUS_VALIDATED);

        $this->assertNotSame($manifest, $validated);
        $this->assertSame(DeploymentManifest::STATUS_PREPARED, $manifest->status);
        $this->assertSame(DeploymentManifest::STATUS_VALIDATED, $validated->status);
        $this->assertSame($manifest->checksum, $validated->checksum);
        $this->assertSame($manifest->uuid, $validated->uuid);
    }

    public function test_to_customer_runtime_package_exposes_only_the_projections(): void
    {
        $manifest = $this->manifest();

        $package = $manifest->toCustomerRuntimePackage();

        $this->assertSame($manifest->runtimeProjectionReferences, $package->assets);
    }

    public function test_only_the_expected_content_fields_exist(): void
    {
        $properties = array_map(
            fn (\ReflectionProperty $p): string => $p->getName(),
            (new \ReflectionClass($this->manifest()))->getProperties()
        );

        $this->assertEqualsCanonicalizing(
            ['uuid', 'siteReference', 'templateReference', 'packageVersion', 'assetReferences', 'runtimeProjectionReferences', 'checksum', 'compatibilityResult', 'status'],
            $properties
        );
    }
}
