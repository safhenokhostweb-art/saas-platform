<?php

namespace Tests\Unit\Services\Modules;

use SaaSPlatform\CoreUtils\Support\ModuleManifestSchema;
use Tests\TestCase;

/**
 * Phase 17-F — Part 8: ModuleManifestSchema scenarios. Pure array-in/
 * array-out — no filesystem, no DB, no ZIP.
 */
class ModuleManifestSchemaTest extends TestCase
{
    private function validManifest(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Forms Module',
            'module_slug' => 'forms',
            'version' => '1.1.0',
            'type' => 'core',
            'namespace' => 'SaaSPlatform\\Modules\\Forms',
            'provider' => 'SaaSPlatform\\Modules\\Forms\\Providers\\FormsServiceProvider',
            'compatibility' => ['php' => '^8.1', 'laravel' => '^10.0', 'saas-platform' => '>=1.0.0'],
            'update_type' => 'minor',
            'dependencies' => ['packages' => [], 'modules' => []],
        ], $overrides);
    }

    // ─── Scenario 1 — valid manifest ────────────────────────────────────────

    public function test_valid_manifest_passes_with_no_errors(): void
    {
        $result = (new ModuleManifestSchema())->validate($this->validManifest());

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['errors']);
        $this->assertEmpty($result['warnings']);
    }

    /** Real module.json fixtures audited in Phase 17-F must never fail this schema. */
    public function test_real_forms_manifest_on_disk_passes(): void
    {
        $manifest = json_decode(
            file_get_contents(base_path('packages/saas-platform/modules/forms/module.json')),
            true
        );

        $result = (new ModuleManifestSchema())->validate($manifest);

        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
    }

    public function test_real_tickets_manifest_on_disk_passes(): void
    {
        $manifest = json_decode(
            file_get_contents(base_path('packages/saas-platform/modules/tickets/module.json')),
            true
        );

        $result = (new ModuleManifestSchema())->validate($manifest);

        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
    }

    public function test_real_education_manifest_on_disk_passes(): void
    {
        $manifest = json_decode(
            file_get_contents(base_path('packages/saas-platform/modules/education/module.json')),
            true
        );

        $result = (new ModuleManifestSchema())->validate($manifest);

        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
    }

    // ─── Scenario 2 — incomplete manifest ──────────────────────────────────

    public function test_missing_required_field_fails(): void
    {
        $manifest = $this->validManifest();
        unset($manifest['module_slug']);

        $result = (new ModuleManifestSchema())->validate($manifest);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('module_slug', implode(' ', $result['errors']));
    }

    public function test_missing_recommended_field_only_warns(): void
    {
        $manifest = $this->validManifest();
        unset($manifest['compatibility']);

        $result = (new ModuleManifestSchema())->validate($manifest);

        $this->assertTrue($result['valid']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_invalid_semver_version_fails(): void
    {
        $result = (new ModuleManifestSchema())->validate($this->validManifest(['version' => 'not-a-version']));

        $this->assertFalse($result['valid']);
    }

    public function test_invalid_module_slug_format_fails(): void
    {
        $result = (new ModuleManifestSchema())->validate($this->validManifest(['module_slug' => 'Has Spaces']));

        $this->assertFalse($result['valid']);
    }

    public function test_malformed_dependencies_shape_only_warns(): void
    {
        $result = (new ModuleManifestSchema())->validate($this->validManifest(['dependencies' => 'not-an-array']));

        $this->assertTrue($result['valid']);
        $this->assertNotEmpty($result['warnings']);
    }

    public function test_valid_uninstall_block_passes(): void
    {
        $result = (new ModuleManifestSchema())->validate($this->validManifest([
            'uninstall' => ['type' => 'soft', 'cleanup' => ['tables', 'files']],
        ]));

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['warnings']);
    }

    public function test_invalid_uninstall_type_only_warns(): void
    {
        $result = (new ModuleManifestSchema())->validate($this->validManifest([
            'uninstall' => ['type' => 'destroy-everything'],
        ]));

        $this->assertTrue($result['valid']);
        $this->assertNotEmpty($result['warnings']);
    }

    /** display_name is deliberately not part of the schema — modules.title (DB) owns it. */
    public function test_display_name_is_not_required_or_recommended(): void
    {
        $manifest = $this->validManifest();
        $this->assertArrayNotHasKey('display_name', $manifest);

        $result = (new ModuleManifestSchema())->validate($manifest);

        $this->assertTrue($result['valid']);
        $this->assertEmpty($result['warnings']);
    }
}
