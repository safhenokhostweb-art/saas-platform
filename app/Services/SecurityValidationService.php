<?php

namespace App\Services;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * PHASE 8.0 — Runtime security validation service.
 *
 * Each check returns an array of findings. Empty array = passed.
 * Each finding: ['severity' => 'CRITICAL|HIGH|MEDIUM|LOW', 'message' => '...', 'location' => '...']
 */
class SecurityValidationService
{
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    // ─── Public check suite ───────────────────────────────────────────────────

    public function checkUnauthenticatedRoutes(): array
    {
        $findings = [];

        foreach ($this->router->getRoutes() as $route) {
            $middleware = $route->middleware();
            $uri        = $route->uri();
            $methods    = implode('|', $route->methods());

            // Only check API routes that mutate state (non-GET)
            if (!Str::startsWith($uri, 'api/')) {
                continue;
            }

            $hasAuth = collect($middleware)->contains(
                fn($m) => Str::contains($m, ['auth', 'tenant.api', 'sanctum'])
            );

            if (!$hasAuth && !in_array('GET', $route->methods())) {
                $findings[] = [
                    'severity' => 'CRITICAL',
                    'message'  => "Unauthenticated mutating API route: [{$methods}] {$uri}",
                    'location' => "routes/api.php",
                ];
            }
        }

        return $findings;
    }

    public function checkPublicStorageLeaks(): array
    {
        $findings = [];
        $publicPath = storage_path('app/public/ticket-attachments');

        if (File::isDirectory($publicPath) && count(File::allFiles($publicPath)) > 0) {
            $findings[] = [
                'severity' => 'HIGH',
                'message'  => 'Ticket attachments found in public storage — should be in private disk',
                'location' => 'storage/app/public/ticket-attachments/',
            ];
        }

        return $findings;
    }

    public function checkMissingTenantScopes(): array
    {
        $findings = [];
        $tenantModels = [
            \SaaSPlatform\Modules\Tickets\Models\Ticket::class     => 'SiteTenantScope',
            \SaaSPlatform\Modules\Forms\Models\FormSubmission::class => 'SiteTenantScope',
        ];

        foreach ($tenantModels as $modelClass => $expectedScope) {
            try {
                $instance = new $modelClass();
                $scopes   = array_keys($instance->getGlobalScopes());
                $hasScope = collect($scopes)->contains(fn($s) => str_contains($s, $expectedScope));

                if (!$hasScope) {
                    $findings[] = [
                        'severity' => 'CRITICAL',
                        'message'  => class_basename($modelClass) . " is missing {$expectedScope} global scope",
                        'location' => $modelClass,
                    ];
                }
            } catch (\Throwable $e) {
                $findings[] = [
                    'severity' => 'HIGH',
                    'message'  => "Cannot verify scope on " . class_basename($modelClass) . ": {$e->getMessage()}",
                    'location' => $modelClass,
                ];
            }
        }

        return $findings;
    }

    public function checkPackageBoundaryViolations(): array
    {
        $findings = [];
        $packages = [
            base_path('packages/saas-platform/modules/tickets/src'),
            base_path('packages/saas-platform/modules/forms/src'),
            base_path('packages/saas-platform/core-utils/src'),
        ];

        $allowed = ['App\Http\Controllers\Controller'];

        foreach ($packages as $pkgRoot) {
            if (!File::isDirectory($pkgRoot)) {
                continue;
            }

            foreach (File::allFiles($pkgRoot) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                $content = File::get($file->getPathname());
                if (preg_match_all('/use App\\\\[A-Z]\w+/', $content, $matches)) {
                    foreach ($matches[0] as $match) {
                        $isAllowed = collect($allowed)->contains(fn($a) => str_contains($match, $a));
                        if (!$isAllowed) {
                            $relative = ltrim(str_replace(base_path(), '', $file->getPathname()), '/\\');
                            $findings[] = [
                                'severity' => 'HIGH',
                                'message'  => "Package imports App\\ class: {$match}",
                                'location' => $relative,
                            ];
                        }
                    }
                }
            }
        }

        return $findings;
    }

    public function checkUnsafeDownloadRoutes(): array
    {
        $findings = [];

        foreach ($this->router->getRoutes() as $route) {
            $uri    = $route->uri();
            $action = $route->getActionName();

            if (!Str::contains(strtolower($uri), ['download', 'attachment', 'storage'])) {
                continue;
            }

            $middleware = $route->middleware();
            $hasAuth    = collect($middleware)->contains(fn($m) => Str::contains($m, ['auth', 'tenant.api']));

            if (!$hasAuth) {
                $findings[] = [
                    'severity' => 'MEDIUM',
                    'message'  => "Potentially unprotected file route: {$uri}",
                    'location' => $action,
                ];
            }
        }

        return $findings;
    }

    public function checkExposedSecrets(): array
    {
        // Delegates to the scan-secrets command logic; here we check env for obvious issues
        $findings = [];

        if (config('app.debug') === true && app()->isProduction()) {
            $findings[] = [
                'severity' => 'CRITICAL',
                'message'  => 'APP_DEBUG=true in production — stack traces exposed to users',
                'location' => '.env / config/app.php',
            ];
        }

        if (empty(config('app.key'))) {
            $findings[] = [
                'severity' => 'CRITICAL',
                'message'  => 'APP_KEY is empty — encrypted values are insecure',
                'location' => '.env',
            ];
        }

        return $findings;
    }

    public function runAll(): array
    {
        return [
            'unauthenticated_routes'     => $this->checkUnauthenticatedRoutes(),
            'public_storage_leaks'       => $this->checkPublicStorageLeaks(),
            'missing_tenant_scopes'      => $this->checkMissingTenantScopes(),
            'package_boundary_violations'=> $this->checkPackageBoundaryViolations(),
            'unsafe_download_routes'     => $this->checkUnsafeDownloadRoutes(),
            'exposed_secrets'            => $this->checkExposedSecrets(),
        ];
    }
}
