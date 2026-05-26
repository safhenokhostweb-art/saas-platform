<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

/**
 * TASK 6 — Runtime Safety Validation
 *
 * Validates all runtime safety aspects of the Forms module:
 * - Queue safety (jobs, listeners, notifications)
 * - Cache safety (config cache, route cache, view cache)
 * - Package discovery (composer autoload, service provider registration)
 * - Multi-tenant runtime isolation
 * - View rendering integrity
 *
 * This service is the CANONICAL runtime validator for the Forms module.
 * It ensures the module operates safely in production under all conditions.
 */
class FormsRuntimeSafetyService
{
    /**
     * Module slug identifier.
     */
    protected const MODULE_SLUG = 'forms';

    /**
     * Required service providers that must be registered.
     */
    protected const REQUIRED_PROVIDERS = [
        \SaaSPlatform\Modules\Forms\Providers\FormsServiceProvider::class,
    ];

    /**
     * Required config files that must be publishable.
     */
    protected const REQUIRED_CONFIGS = [
        'forms.php',
        'module-manifest-forms.php',
    ];

    /**
     * Required migration files.
     */
    protected const REQUIRED_MIGRATIONS = [
        'create_forms_table',
        'create_form_fields_table',
        'create_form_submissions_table',
        'create_form_site_table',
    ];

    /**
     * Run full runtime safety validation.
     *
     * @return array{safe: bool, checks: array<string, array{passed: bool, detail?: string, error?: string}>}
     */
    public function validateAll(): array
    {
        $checks = [];

        // 1. Queue safety
        $checks['queue_safety'] = $this->checkQueueSafety();

        // 2. Cache safety
        $checks['config_cache_safety'] = $this->checkConfigCacheSafety();
        $checks['route_cache_safety'] = $this->checkRouteCacheSafety();
        $checks['view_cache_safety'] = $this->checkViewCacheSafety();

        // 3. Package discovery
        $checks['package_discovery'] = $this->checkPackageDiscovery();

        // 4. Service provider registration
        $checks['service_provider_registration'] = $this->checkServiceProviderRegistration();

        // 5. Multi-tenant isolation
        $checks['tenant_isolation'] = $this->checkTenantIsolation();

        // 6. View rendering integrity
        $checks['view_rendering'] = $this->checkViewRendering();

        // 7. Route integrity
        $checks['route_integrity'] = $this->checkRouteIntegrity();

        // 8. Database connectivity
        $checks['database_connectivity'] = $this->checkDatabaseConnectivity();

        // 9. Config integrity
        $checks['config_integrity'] = $this->checkConfigIntegrity();

        // 10. Migration state
        $checks['migration_state'] = $this->checkMigrationState();

        $allSafe = collect($checks)->every(fn($c) => $c['passed']);

        Log::info('forms.runtime_safety.validation_complete', [
            'all_safe' => $allSafe,
            'check_count' => count($checks),
            'failed_checks' => collect($checks)->filter(fn($c) => !$c['passed'])->keys()->toArray(),
        ]);

        return [
            'safe' => $allSafe,
            'checks' => $checks,
        ];
    }

    /**
     * Validate queue safety.
     * Checks that no Forms jobs/listeners reference deleted models or stale data.
     */
    protected function checkQueueSafety(): array
    {
        $issues = [];

        // Check that Form model exists and has proper table
        if (!class_exists(\SaaSPlatform\Modules\Forms\Models\Form::class)) {
            $issues[] = 'Form model class not found';
        }

        if (!class_exists(\SaaSPlatform\Modules\Forms\Models\FormField::class)) {
            $issues[] = 'FormField model class not found';
        }

        if (!class_exists(\SaaSPlatform\Modules\Forms\Models\FormSubmission::class)) {
            $issues[] = 'FormSubmission model class not found';
        }

        // Check that models use SoftDeletes (queue safety for deleted models)
        if (class_exists(\SaaSPlatform\Modules\Forms\Models\Form::class)) {
            $uses = trait_uses_recursive(\SaaSPlatform\Modules\Forms\Models\Form::class);
            if (!in_array('Illuminate\Database\Eloquent\SoftDeletes', $uses)) {
                $issues[] = 'Form model does not use SoftDeletes - queue jobs may fail on deleted records';
            }
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0 ? 'All models exist and use SoftDeletes' : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * Validate config cache safety.
     * Ensures config() calls in the module resolve correctly when config is cached.
     */
    protected function checkConfigCacheSafety(): array
    {
        $issues = [];

        // Check that forms config key exists
        if (config('forms') === null) {
            $issues[] = 'config("forms") returns null - config not merged or cached incorrectly';
        }

        // Check that layouts config resolves
        $adminLayout = config('forms.layouts.admin');
        if (empty($adminLayout)) {
            $issues[] = 'config("forms.layouts.admin") is empty';
        }

        $siteLayout = config('forms.layouts.site');
        if (empty($siteLayout)) {
            $issues[] = 'config("forms.layouts.site") is empty';
        }

        // Check that module manifest config exists
        if (config('module-manifest-forms') === null) {
            $issues[] = 'config("module-manifest-forms") returns null';
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0 ? 'All config keys resolve correctly' : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * Validate route cache safety.
     * Ensures all module routes can be cached without errors.
     */
    protected function checkRouteCacheSafety(): array
    {
        $issues = [];

        // Check that route files exist
        $webRoutes = __DIR__ . '/../../routes/web.php';
        if (!File::exists($webRoutes)) {
            $issues[] = 'routes/web.php not found';
        }

        // Check that route names don't conflict
        $routeCollection = Route::getRoutes();
        $formsRoutes = collect($routeCollection)->filter(function ($route) {
            $name = $route->getName() ?? '';
            return str_starts_with($name, 'admin.forms.')
                || str_starts_with($name, 'site.forms.')
                || str_starts_with($name, 'forms.');
        });

        if ($formsRoutes->isEmpty()) {
            $issues[] = 'No Forms routes registered - service provider may not be loaded';
        }

        // Check for duplicate route names
        $names = $formsRoutes->map(fn($r) => $r->getName())->filter();
        $duplicates = $names->duplicates();
        if ($duplicates->isNotEmpty()) {
            $issues[] = 'Duplicate route names found: ' . $duplicates->implode(', ');
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0 ? $formsRoutes->count() . ' Forms routes registered, no conflicts' : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * Validate view cache safety.
     * Ensures all module views compile correctly.
     */
    protected function checkViewCacheSafety(): array
    {
        $issues = [];

        // Check that view directory exists
        $viewPath = __DIR__ . '/../../resources/views';
        if (!File::isDirectory($viewPath)) {
            $issues[] = 'Views directory not found at ' . $viewPath;
            return [
                'passed' => false,
                'error' => implode('; ', $issues),
            ];
        }

        // Check that views are discoverable via the 'forms' namespace
        $hints = View::getFinder()->getHints();
        $formsHints = $hints['forms'] ?? [];
        if (empty($formsHints)) {
            $issues[] = 'No view hints registered for "forms" namespace';
        }

        // Check that critical views exist
        $criticalViews = [
            'admin.forms.index',
            'admin.forms.create',
            'admin.forms.edit',
            'site.forms.show',
        ];

        foreach ($criticalViews as $view) {
            if (!View::exists($view)) {
                $issues[] = "View '{$view}' does not exist";
            }
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0 ? 'All critical views exist and are discoverable' : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * Validate package discovery.
     * Ensures the Forms module is properly registered in composer and discovered.
     */
    protected function checkPackageDiscovery(): array
    {
        $issues = [];

        // Check that the service provider is registered in the application
        $appProviders = $this->getRegisteredProviders();
        $missingProviders = [];

        foreach (self::REQUIRED_PROVIDERS as $provider) {
            if (!in_array($provider, $appProviders, true)) {
                $missingProviders[] = $provider;
            }
        }

        if (!empty($missingProviders)) {
            $issues[] = 'Required providers not registered: ' . implode(', ', $missingProviders);
        }

        // Check that the package is in composer.json
        $composerPath = base_path('composer.json');
        if (File::exists($composerPath)) {
            $composer = json_decode(File::get($composerPath), true);
            $required = $composer['require'] ?? [];
            if (!isset($required['saas-platform/modules-forms'])) {
                $issues[] = 'Package saas-platform/modules-forms not found in composer.json require';
            }
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0 ? 'Package discovered and provider registered' : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * Validate service provider registration.
     */
    protected function checkServiceProviderRegistration(): array
    {
        $issues = [];

        $appProviders = $this->getRegisteredProviders();

        foreach (self::REQUIRED_PROVIDERS as $provider) {
            if (!in_array($provider, $appProviders, true)) {
                $issues[] = "{$provider} is not registered";
            }
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0 ? 'FormsServiceProvider is properly registered' : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * Validate multi-tenant runtime isolation.
     * Ensures Forms data is properly scoped per tenant.
     */
    protected function checkTenantIsolation(): array
    {
        $issues = [];

        // Check that Form model has site_id or tenant_id column
        if (Schema::hasTable('forms')) {
            $columns = Schema::getColumnListing('forms');
            $hasTenantColumn = in_array('site_id', $columns, true)
                || in_array('reseller_id', $columns, true)
                || in_array('tenant_id', $columns, true);

            if (!$hasTenantColumn) {
                $issues[] = 'forms table has no tenant-scoping column (site_id/reseller_id/tenant_id)';
            }
        }

        // Check that form_site pivot table exists (multi-tenant assignment)
        if (!Schema::hasTable('form_site')) {
            $issues[] = 'form_site pivot table does not exist - multi-tenant assignment not possible';
        }

        // Check that FormSubmission has form_id for scoping
        if (Schema::hasTable('form_submissions')) {
            $columns = Schema::getColumnListing('form_submissions');
            if (!in_array('form_id', $columns, true)) {
                $issues[] = 'form_submissions table has no form_id column - submissions not scoped to forms';
            }
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0 ? 'Tenant isolation columns present' : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * Validate view rendering integrity.
     * Ensures views render without errors using config-based layouts.
     */
    protected function checkViewRendering(): array
    {
        $issues = [];

        // Check that admin layout view exists
        $adminLayout = config('forms.layouts.admin', 'layouts.admin');
        if (!View::exists($adminLayout)) {
            $issues[] = "Admin layout '{$adminLayout}' does not exist";
        }

        // Check that site layout view exists
        $siteLayout = config('forms.layouts.site', 'themes.default.layout');
        if (!View::exists($siteLayout)) {
            $issues[] = "Site layout '{$siteLayout}' does not exist";
        }

        // Check that component partial exists
        $guideBox = config('forms.components.module-guide-box', 'admin.partials.module-guide-box');
        if (!View::exists($guideBox)) {
            $issues[] = "Module guide component '{$guideBox}' does not exist";
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0 ? 'All layout views and components exist' : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * Validate route integrity.
     */
    protected function checkRouteIntegrity(): array
    {
        $issues = [];

        $routeCollection = Route::getRoutes();
        $formsRoutes = collect($routeCollection)->filter(function ($route) {
            $name = $route->getName() ?? '';
            return str_starts_with($name, 'admin.forms.')
                || str_starts_with($name, 'site.forms.')
                || str_starts_with($name, 'forms.');
        });

        // Check that CRUD routes exist
        $requiredRoutes = [
            'admin.forms.index',
            'admin.forms.create',
            'admin.forms.store',
            'admin.forms.edit',
            'admin.forms.update',
            'admin.forms.destroy',
        ];

        $routeNames = $formsRoutes->map(fn($r) => $r->getName())->values()->toArray();

        foreach ($requiredRoutes as $route) {
            if (!in_array($route, $routeNames, true)) {
                $issues[] = "Required route '{$route}' not found";
            }
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0 ? 'All CRUD routes registered' : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * Validate database connectivity for Forms tables.
     */
    protected function checkDatabaseConnectivity(): array
    {
        $issues = [];

        $requiredTables = ['forms', 'form_fields', 'form_submissions', 'form_site'];

        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                $issues[] = "Required table '{$table}' does not exist";
            }
        }

        // Check that module_schema_versions table exists (lifecycle tracking)
        if (!Schema::hasTable('module_schema_versions')) {
            $issues[] = "Lifecycle tracking table 'module_schema_versions' does not exist";
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0 ? 'All required database tables exist' : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * Validate config integrity.
     */
    protected function checkConfigIntegrity(): array
    {
        $issues = [];

        // Check that published config exists
        $configPath = config_path('forms.php');
        if (!File::exists($configPath)) {
            $issues[] = 'Published config file config/forms.php not found';
        }

        // Check that published manifest exists
        $manifestPath = config_path('module-manifest-forms.php');
        if (!File::exists($manifestPath)) {
            $issues[] = 'Published manifest config/module-manifest-forms.php not found';
        }

        // Check that package config exists
        $packageConfig = __DIR__ . '/../../config/forms.php';
        if (!File::exists($packageConfig)) {
            $issues[] = 'Package config file not found';
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0 ? 'All config files present' : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * Validate migration state.
     */
    protected function checkMigrationState(): array
    {
        $issues = [];

        // Check that migration directory exists
        $migrationPath = __DIR__ . '/../../database/migrations';
        if (!File::isDirectory($migrationPath)) {
            $issues[] = 'Migrations directory not found';
            return [
                'passed' => false,
                'error' => implode('; ', $issues),
            ];
        }

        // Check that migration files exist
        $migrationFiles = File::files($migrationPath);
        if (empty($migrationFiles)) {
            $issues[] = 'No migration files found in package';
        }

        // Check that published migrations exist in app database/migrations
        $appMigrations = File::files(database_path('migrations'));
        $formsMigrations = collect($appMigrations)->filter(function ($file) {
            $name = $file->getFilename();
            return str_contains($name, 'forms')
                || str_contains($name, 'form_fields')
                || str_contains($name, 'form_submissions')
                || str_contains($name, 'form_site');
        });

        if ($formsMigrations->isEmpty()) {
            $issues[] = 'No Forms migration files found in database/migrations/';
        }

        return [
            'passed' => count($issues) === 0,
            'detail' => count($issues) === 0 ? $formsMigrations->count() . ' migration files found' : null,
            'error' => count($issues) > 0 ? implode('; ', $issues) : null,
        ];
    }

    /**
     * Get all registered service providers from the application.
     *
     * @return array<int, string>
     */
    protected function getRegisteredProviders(): array
    {
        // Use the application's booted provider list (framework-native, no App\ config dependency)
        $app = app();
        if (method_exists($app, 'getLoadedProviders')) {
            return array_keys($app->getLoadedProviders());
        }

        // Last-resort fallback: read host config if available
        $providers = config('app.providers', []);
        return is_array($providers) ? $providers : [];
    }
}
