<?php

namespace Tests\Unit\Services\Installer;

use App\Services\Installer\LocalPcPackageBuilder;
use Tests\TestCase;
use ZipArchive;

class LocalPcPackageBuilderTest extends TestCase
{
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_package_without_manifest_signature_is_rejected(): void
    {
        $manifest = [
            'files' => [
                'database/platform.sql.gz' => hash('sha256', str_repeat('database', 20)),
            ],
        ];

        $package = $this->createPackage($manifest);
        $result = app(LocalPcPackageBuilder::class)->extractAndVerify($package);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('manifest_signature', $result['message']);
    }

    public function test_package_with_unlisted_platform_database_is_rejected(): void
    {
        $manifest = ['files' => []];
        $manifest['manifest_signature'] = hash(
            'sha256',
            json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $package = $this->createPackage($manifest);
        $result = app(LocalPcPackageBuilder::class)->extractAndVerify($package);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('checksum manifest', $result['message']);
    }

    private function createPackage(array $manifest): string
    {
        $path = sys_get_temp_dir() . '/local-pc-package-' . bin2hex(random_bytes(8)) . '.zip';
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString(
            'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $zip->addFromString('database/platform.sql.gz', str_repeat('database', 20));
        $zip->close();
        $this->temporaryFiles[] = $path;

        return $path;
    }
}
