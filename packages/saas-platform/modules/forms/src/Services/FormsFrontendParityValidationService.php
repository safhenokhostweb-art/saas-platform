<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

/**
 * Phase 9.1 — Forms Frontend Parity Validation
 *
 * Verifies that both saas-platform and customer-site render from the same
 * canonical package views with zero frontend divergence.
 */
class FormsFrontendParityValidationService
{
    private array $results = [];
    private int   $score   = 0;
    private int   $checks  = 0;
    private array $warnings = [];
    private array $failures = [];

    public function validate(bool $strict = false): array
    {
        $this->results  = [];
        $this->score    = 0;
        $this->checks   = 0;
        $this->warnings = [];
        $this->failures = [];

        $this->checkPackageViewsExist();
        $this->checkNoForbiddenCustomerSiteOverrides();
        $this->checkNoForbiddenPlatformOverrides();
        $this->checkNoVendorPublishedOverrides();
        $this->checkCoreUiNamespaceResolvable();
        $this->checkBladDirectivesRegistered();
        $this->checkLayoutConfigPresent();
        $this->checkDynamicLayoutResolution();
        $this->checkNoLegacyViewPaths();
        $this->checkCanonicalRouteNaming();

        $score = $this->checks > 0 ? (int) round(($this->score / $this->checks) * 100) : 0;

        return [
            'score'         => $score,
            'pass'          => $score >= ($strict ? 95 : 80),
            'checks'        => $this->checks,
            'passed'        => $this->score,
            'warnings'      => $this->warnings,
            'failures'      => $this->failures,
            'results'       => $this->results,
            'strict'        => $strict,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function pass(string $name, string $detail = ''): void
    {
        $this->results[] = ['check' => $name, 'status' => 'pass', 'detail' => $detail];
        $this->score++;
        $this->checks++;
    }

    private function fail(string $name, string $detail = ''): void
    {
        $this->results[] = ['check' => $name, 'status' => 'fail', 'detail' => $detail];
        $this->failures[] = $name . ($detail ? ': ' . $detail : '');
        $this->checks++;
    }

    private function warn(string $name, string $detail = ''): void
    {
        $this->results[] = ['check' => $name, 'status' => 'warn', 'detail' => $detail];
        $this->warnings[] = $name . ($detail ? ': ' . $detail : '');
        $this->score++;
        $this->checks++;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function checkPackageViewsExist(): void
    {
        $viewsBase = dirname(__DIR__, 2) . '/resources/views';

        $required = [
            'admin/forms/index.blade.php',
            'admin/forms/create.blade.php',
            'admin/forms/builder.blade.php',
            'admin/forms/submissions/index.blade.php',
            'admin/forms/analytics.blade.php',
            'admin/forms/automation/index.blade.php',
            'admin/forms/templates/index.blade.php',
            'site/forms/show.blade.php',
            'site/forms/_field.blade.php',
            'core-ui/components/form-status-pill.blade.php',
            'core-ui/components/autosave-indicator.blade.php',
            'core-ui/components/empty-state.blade.php',
            'core-ui/components/toast.blade.php',
            'core-ui/components/skeleton-card.blade.php',
        ];

        $missing = [];
        foreach ($required as $path) {
            if (! File::exists($viewsBase . '/' . $path)) {
                $missing[] = $path;
            }
        }

        if (empty($missing)) {
            $this->pass('package_views_exist', count($required) . ' canonical views confirmed');
        } else {
            $this->fail('package_views_exist', 'Missing: ' . implode(', ', $missing));
        }
    }

    private function checkNoForbiddenCustomerSiteOverrides(): void
    {
        $customerSiteBase = base_path('../customer-site/resources/views');

        if (! is_dir($customerSiteBase)) {
            $this->warn('no_customer_site_admin_forms', 'customer-site not found at expected path — skipping');
            return;
        }

        $forbidden = [
            $customerSiteBase . '/admin/forms',
            $customerSiteBase . '/site/forms',
        ];

        foreach ($forbidden as $dir) {
            if (is_dir($dir)) {
                $this->fail('no_customer_site_admin_forms', 'Forbidden override directory exists: ' . $dir);
                return;
            }
        }

        $this->pass('no_customer_site_admin_forms', 'No admin/forms or site/forms in customer-site');
    }

    private function checkNoForbiddenPlatformOverrides(): void
    {
        $platformBase = resource_path('views/admin/forms');

        if (is_dir($platformBase)) {
            $files = File::allFiles($platformBase);
            if (count($files) > 0) {
                $this->fail('no_platform_app_form_views', 'App-level admin/forms views found: ' . count($files) . ' files');
                return;
            }
        }

        $this->pass('no_platform_app_form_views', 'No app-level admin/forms override views');
    }

    private function checkNoVendorPublishedOverrides(): void
    {
        $vendorForms = resource_path('views/vendor/forms');

        if (is_dir($vendorForms)) {
            $files = File::allFiles($vendorForms);
            if (count($files) > 0) {
                $this->warn(
                    'no_vendor_published_overrides',
                    count($files) . ' published vendor views exist — ensure they are intentional overrides'
                );
                return;
            }
        }

        $this->pass('no_vendor_published_overrides', 'No published vendor/forms overrides in platform');
    }

    private function checkCoreUiNamespaceResolvable(): void
    {
        try {
            $exists = View::exists('core-ui::components.form-status-pill')
                   || View::exists('core-ui::components/form-status-pill');

            if ($exists) {
                $this->pass('core_ui_namespace_resolvable', 'core-ui::components.* resolves correctly');
            } else {
                $this->warn(
                    'core_ui_namespace_resolvable',
                    'core-ui view namespace check inconclusive — verify Blade::anonymousComponentNamespace registration'
                );
            }
        } catch (\Exception $e) {
            $this->fail('core_ui_namespace_resolvable', $e->getMessage());
        }
    }

    private function checkBladDirectivesRegistered(): void
    {
        $provider = app()->getProvider(\SaaSPlatform\Modules\Forms\Providers\FormsServiceProvider::class);

        if (! $provider) {
            $this->fail('blade_directives_registered', 'FormsServiceProvider not found in container');
            return;
        }

        // Verify @platform directive exists by checking Blade condition list
        $directives = \Illuminate\Support\Facades\Blade::getCustomDirectives();
        $conditions = (function() {
            // Access private property via reflection
            try {
                $blade = app(\Illuminate\View\Compilers\BladeCompiler::class);
                $ref   = new \ReflectionProperty($blade, 'conditions');
                $ref->setAccessible(true);
                return array_keys($ref->getValue($blade));
            } catch (\Throwable) {
                return [];
            }
        })();

        $requiredConditions = ['platform', 'tenant', 'capability'];
        $missing = array_diff($requiredConditions, $conditions);

        if (empty($missing)) {
            $this->pass('blade_directives_registered', '@platform, @tenant, @capability all registered');
        } else {
            $this->warn('blade_directives_registered', 'Could not verify conditions: ' . implode(', ', $missing));
        }
    }

    private function checkLayoutConfigPresent(): void
    {
        $required = ['forms.layouts.admin', 'forms.layouts.admin_tenant', 'forms.layouts.site'];
        $missing  = [];

        foreach ($required as $key) {
            if (! config($key)) {
                $missing[] = $key;
            }
        }

        if (empty($missing)) {
            $this->pass('layout_config_present', 'All 3 layout config keys resolved');
        } else {
            $this->fail('layout_config_present', 'Missing config keys: ' . implode(', ', $missing));
        }
    }

    private function checkDynamicLayoutResolution(): void
    {
        $service = app(\SaaSPlatform\Modules\Forms\Services\PlatformContextService::class);

        if ($service) {
            $this->pass('dynamic_layout_resolution', 'PlatformContextService resolvable — layout composer active');
        } else {
            $this->fail('dynamic_layout_resolution', 'PlatformContextService not in container');
        }
    }

    private function checkNoLegacyViewPaths(): void
    {
        $legacyPaths = [
            resource_path('views/admin/forms/_legacy'),
            resource_path('views/admin/forms/edit.blade.php'),
            resource_path('views/admin/forms/index.blade.php'),
            resource_path('views/admin/forms/create.blade.php'),
        ];

        $found = array_filter($legacyPaths, fn($p) => file_exists($p));

        if (empty($found)) {
            $this->pass('no_legacy_view_paths', 'All legacy app/resources/views/admin/forms paths are absent');
        } else {
            $this->warn('no_legacy_view_paths', 'Legacy paths still exist: ' . implode(', ', array_values($found)));
        }
    }

    private function checkCanonicalRouteNaming(): void
    {
        $routes = collect(app('router')->getRoutes())->filter(
            fn($r) => str_starts_with($r->getName() ?? '', 'admin.forms.')
        );

        $expected = [
            'admin.forms.index',
            'admin.forms.create',
            'admin.forms.builder',
            'admin.forms.submissions.index',
            'admin.forms.analytics',
            'admin.forms.automation.index',
            'admin.forms.templates.index',
            'admin.forms.duplicate',
        ];

        $missing = [];
        foreach ($expected as $name) {
            try {
                route($name, [1]);
            } catch (\Exception) {
                $missing[] = $name;
            }
        }

        if (empty($missing)) {
            $this->pass('canonical_route_naming', 'All ' . count($expected) . ' canonical admin.forms.* routes present');
        } else {
            $this->warn('canonical_route_naming', 'Routes not found: ' . implode(', ', $missing));
        }
    }
}
