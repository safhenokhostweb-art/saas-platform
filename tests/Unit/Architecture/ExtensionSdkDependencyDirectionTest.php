<?php

namespace Tests\Unit\Architecture;

use Tests\TestCase;

/**
 * Phase 3G-B-2 — static assertion of the durable cross-repo dependency
 * boundary this phase establishes: saas-platform must never depend on
 * customer-site's App\* namespace, and the shared
 * packages/saas-platform/modules/extension-sdk package must remain usable
 * without booting Laravel (no App\*, no Illuminate\* dependency at all).
 */
class ExtensionSdkDependencyDirectionTest extends TestCase
{
    public function test_root_composer_never_references_a_customer_site_path(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        foreach ($composer['repositories'] ?? [] as $repository) {
            $this->assertStringNotContainsStringIgnoringCase(
                'customer-site',
                $repository['url'] ?? '',
                'saas-platform composer.json must never reference a customer-site path repository.'
            );
        }
    }

    public function test_extension_sdk_package_composer_depends_on_nothing_but_php(): void
    {
        $package = json_decode(
            file_get_contents(base_path('packages/saas-platform/modules/extension-sdk/composer.json')),
            true
        );

        $this->assertSame(['php'], array_keys($package['require'] ?? []));
        $this->assertArrayNotHasKey('extra', $package);
    }

    public function test_extension_sdk_package_source_never_references_app_or_illuminate_namespaces(): void
    {
        $dir = base_path('packages/saas-platform/modules/extension-sdk/src');
        $files = glob($dir . '/*.php');

        $this->assertNotEmpty($files, 'expected the extension-sdk package to contain source files');

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            $this->assertStringNotContainsString('namespace App', $contents, "{$file} must not declare an App namespace");
            $this->assertStringNotContainsString('use App\\', $contents, "{$file} must not import from App\\*");
            $this->assertStringNotContainsString('Illuminate\\', $contents, "{$file} must not reference Illuminate\\* (Laravel-independent)");
            $this->assertStringNotContainsString('use Eloquent', $contents, "{$file} must not reference Eloquent");
        }
    }

    public function test_extension_sdk_classes_are_usable_without_the_laravel_container(): void
    {
        // Constructed with `new self(...)`/plain statics, not app()->make() —
        // proves no framework boot is required to use this package's DTOs.
        $identity = \SaaSPlatform\ExtensionSdk\ExtensionProductIdentity::fromParts('acme', 'gallery-pro');

        $this->assertSame('acme/gallery-pro', $identity->toString());
    }
}
