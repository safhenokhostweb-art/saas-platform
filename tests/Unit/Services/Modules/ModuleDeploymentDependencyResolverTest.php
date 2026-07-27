<?php

namespace Tests\Unit\Services\Modules;

use App\Models\Customer;
use App\Models\Module;
use App\Models\Site;
use App\Services\Modules\ModuleDeploymentDependencyResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;
use Tests\TestCase;

/**
 * Phase 17-F — Part 8: ModuleDeploymentDependencyResolver scenarios.
 * ModuleVersionRegistry is mocked (per the existing createMock +
 * $this->app->instance() pattern already used elsewhere in this suite,
 * e.g. ScanRuntimeAnomaliesCommandTest) since it reads module.json from
 * disk — mocking avoids depending on real, mutable package files for
 * dependency-graph scenarios.
 */
class ModuleDeploymentDependencyResolverTest extends TestCase
{
    use RefreshDatabase;

    private function makeSite(): Site
    {
        $customer = Customer::create(['name' => 'مشتری تست', 'mobile' => '0912' . random_int(1000000, 9999999)]);

        return Site::create([
            'customer_id' => $customer->id, 'title' => 'سایت تست',
            'domain' => 'test-' . uniqid('', true) . '.example.com', 'is_active' => true,
        ]);
    }

    private function makeModule(string $slug, string $type = Module::TYPE_CORE): Module
    {
        return Module::create(['slug' => $slug, 'title' => $slug, 'is_active' => true, 'version' => '1.0.0', 'type' => $type]);
    }

    private function activateOnSite(Site $site, Module $module, ?string $installedVersion = '1.0.0'): void
    {
        DB::table('site_modules')->insert([
            'site_id' => $site->id, 'module_id' => $module->id, 'is_active' => true,
            'installed_version' => $installedVersion, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, array{modules: string[]}>  $dependenciesBySlug
     */
    private function resolverWithDependencies(array $dependenciesBySlug): ModuleDeploymentDependencyResolver
    {
        $registry = $this->createMock(ModuleVersionRegistry::class);
        $registry->method('dependencies')->willReturnCallback(
            fn (string $slug) => $dependenciesBySlug[$slug] ?? ['packages' => [], 'modules' => []]
        );
        $this->app->instance(ModuleVersionRegistry::class, $registry);

        return new ModuleDeploymentDependencyResolver($registry);
    }

    // ─── Scenario: dependency satisfied ────────────────────────────────────

    public function test_dependency_installed_and_active_passes(): void
    {
        $site = $this->makeSite();
        $dep = $this->makeModule('core-users');
        $crm = $this->makeModule('crm-pkg');
        $this->activateOnSite($site, $dep);

        $resolver = $this->resolverWithDependencies(['crm-pkg' => ['modules' => ['core-users']]]);
        $result = $resolver->resolve($site, $crm);

        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
    }

    // ─── Scenario 6 — missing dependency ───────────────────────────────────

    public function test_missing_dependency_is_rejected(): void
    {
        $site = $this->makeSite();
        $this->makeModule('core-users'); // exists, but never activated on the site
        $crm = $this->makeModule('crm-pkg');

        $resolver = $this->resolverWithDependencies(['crm-pkg' => ['modules' => ['core-users']]]);
        $result = $resolver->resolve($site, $crm);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('core-users', implode(' ', $result['errors']));
    }

    public function test_dependency_module_that_does_not_exist_at_all_is_rejected(): void
    {
        $site = $this->makeSite();
        $crm = $this->makeModule('crm-pkg');

        $resolver = $this->resolverWithDependencies(['crm-pkg' => ['modules' => ['ghost-module']]]);
        $result = $resolver->resolve($site, $crm);

        $this->assertFalse($result['valid']);
    }

    // ─── Scenario 7 — dependency version conflict ──────────────────────────

    public function test_dependency_version_below_constraint_is_rejected(): void
    {
        $site = $this->makeSite();
        $dep = $this->makeModule('core-users');
        $crm = $this->makeModule('crm-pkg');
        $this->activateOnSite($site, $dep, installedVersion: '0.9.0');

        $resolver = $this->resolverWithDependencies(['crm-pkg' => ['modules' => ['core-users>=1.0']]]);
        $result = $resolver->resolve($site, $crm);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Version conflict', implode(' ', $result['errors']));
    }

    public function test_dependency_version_meeting_constraint_passes(): void
    {
        $site = $this->makeSite();
        $dep = $this->makeModule('core-users');
        $crm = $this->makeModule('crm-pkg');
        $this->activateOnSite($site, $dep, installedVersion: '1.2.0');

        $resolver = $this->resolverWithDependencies(['crm-pkg' => ['modules' => ['core-users>=1.0']]]);
        $result = $resolver->resolve($site, $crm);

        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
    }

    // ─── Scenario 8 — circular dependency ──────────────────────────────────

    public function test_circular_dependency_is_rejected(): void
    {
        $site = $this->makeSite();
        $a = $this->makeModule('module-a');
        $this->makeModule('module-b');

        $resolver = $this->resolverWithDependencies([
            'module-a' => ['modules' => ['module-b']],
            'module-b' => ['modules' => ['module-a']],
        ]);
        $result = $resolver->resolve($site, $a);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Circular', implode(' ', $result['errors']));
    }

    // ─── Rule 2 — internal-type dependency always considered satisfied ────

    public function test_internal_type_dependency_is_always_considered_satisfied(): void
    {
        $site = $this->makeSite();
        $this->makeModule('platform-core', Module::TYPE_INTERNAL); // never gets a site_modules row
        $crm = $this->makeModule('crm-pkg');

        $resolver = $this->resolverWithDependencies(['crm-pkg' => ['modules' => ['platform-core']]]);
        $result = $resolver->resolve($site, $crm);

        $this->assertTrue($result['valid'], implode('; ', $result['errors']));
    }

    public function test_no_dependencies_declared_passes(): void
    {
        $site = $this->makeSite();
        $crm = $this->makeModule('crm-pkg');

        $resolver = $this->resolverWithDependencies([]);
        $result = $resolver->resolve($site, $crm);

        $this->assertTrue($result['valid']);
    }
}
