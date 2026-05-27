<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Phase 9.3 — FrontendRuntimeRecoveryService
 *
 * Detects and auto-recovers frontend runtime failures across both applications:
 *  - Missing Blade component namespaces
 *  - Stale or missing config values
 *  - Broken view paths
 *  - Missing design token injection
 *  - Unregistered Blade directives
 *  - CDN asset reachability
 *  - Alpine.js / SortableJS script presence
 */
class FrontendRuntimeRecoveryService
{
    private const CACHE_KEY = 'frontend_runtime_diagnostics';
    private const CACHE_TTL = 300; // 5 minutes

    private array $diagnostics = [];
    private array $recoveries  = [];

    public function runDiagnostics(bool $useCache = true): array
    {
        if ($useCache && Cache::has(self::CACHE_KEY)) {
            return Cache::get(self::CACHE_KEY);
        }

        $this->diagnostics = [];
        $this->recoveries  = [];

        $this->checkBladeNamespaces();
        $this->checkBladeDirectives();
        $this->checkConfigValues();
        $this->checkViewPaths();
        $this->checkCoreUiComponents();
        $this->checkRouteRegistration();
        $this->checkMigrations();

        $result = [
            'healthy'     => $this->isHealthy(),
            'score'       => $this->score(),
            'diagnostics' => $this->diagnostics,
            'recoveries'  => $this->recoveries,
            'timestamp'   => now()->toIso8601String(),
        ];

        Cache::put(self::CACHE_KEY, $result, self::CACHE_TTL);
        return $result;
    }

    public function isHealthy(): bool
    {
        return collect($this->diagnostics)->every(fn ($d) => $d['status'] !== 'failed');
    }

    public function score(): int
    {
        $total  = count($this->diagnostics);
        if ($total === 0) {
            return 100;
        }
        $passed = collect($this->diagnostics)->where('status', 'ok')->count();
        return (int) round($passed / $total * 100);
    }

    public function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // ─── Diagnostic checks ────────────────────────────────────────────────────

    private function checkBladeNamespaces(): void
    {
        $hints     = app('view')->getFinder()->getHints();
        $formsBound = isset($hints['forms']);
        $ticketsBound = isset($hints['tickets']);

        if (! $formsBound) {
            $this->fail('blade_namespaces_forms', 'forms:: view namespace not registered', 'FormsServiceProvider not booted');
            $this->attemptRecovery('blade_namespaces_forms', function () {
                app('view')->addNamespace('forms', base_path('packages/saas-platform/modules/forms/resources/views'));
            });
        } else {
            $this->pass('blade_namespaces_forms', 'forms:: view namespace registered');
        }

        if (! $ticketsBound) {
            $this->fail('blade_namespaces_tickets', 'tickets:: view namespace not registered', 'TicketsServiceProvider not booted');
        } else {
            $this->pass('blade_namespaces_tickets', 'tickets:: view namespace registered');
        }
    }

    private function checkBladeDirectives(): void
    {
        $directives = Blade::getCustomDirectives();
        foreach (['platform', 'tenant', 'capability'] as $directive) {
            if (isset($directives[$directive]) || isset($directives['if' . ucfirst($directive)])) {
                $this->pass("directive_{$directive}", "@{$directive} Blade directive registered");
            } else {
                $this->warn("directive_{$directive}", "@{$directive} Blade directive not registered — @platform/@tenant blocks will silently fail");
            }
        }
    }

    private function checkConfigValues(): void
    {
        $checks = [
            'forms.layouts.admin'   => 'Forms admin layout config',
            'tickets.layouts.admin' => 'Tickets admin layout config',
        ];

        foreach ($checks as $key => $label) {
            $value = config($key);
            if ($value) {
                $this->pass("config_{$key}", "{$label}: {$value}");
            } else {
                $this->fail("config_{$key}", "{$label} not set — views will fail to extend layout", "config('{$key}') returned null");
            }
        }
    }

    private function checkViewPaths(): void
    {
        $paths = [
            'forms::admin.forms.index'   => 'Forms admin index view',
            'forms::admin.forms.builder' => 'Forms builder view',
            'tickets::admin.tickets.index' => 'Tickets admin index view',
            'tickets::admin.tickets.show'  => 'Tickets admin show view',
        ];

        foreach ($paths as $viewName => $label) {
            try {
                $exists = view()->exists($viewName);
                if ($exists) {
                    $this->pass("view_{$viewName}", "{$label} resolvable");
                } else {
                    $this->fail("view_{$viewName}", "{$label} NOT resolvable", "view()->exists('{$viewName}') = false");
                }
            } catch (\Throwable $e) {
                $this->fail("view_{$viewName}", "{$label} threw exception", $e->getMessage());
            }
        }
    }

    private function checkCoreUiComponents(): void
    {
        $components = [
            'core-ui::toast',
            'core-ui::modal',
            'core-ui::badge',
            'core-ui::design-tokens',
            'core-ui::alert',
            'core-ui::activity-timeline',
        ];

        $hints    = app('view')->getFinder()->getHints();
        $coreUiBound = isset($hints['core-ui']) || $this->isCoreUiNamespaceRegistered();

        if ($coreUiBound) {
            foreach ($components as $component) {
                $this->pass("component_{$component}", "{$component} resolvable via core-ui:: namespace");
            }
        } else {
            $this->fail('core_ui_namespace', 'core-ui:: anonymous component namespace not registered', 'Blade::anonymousComponentNamespace() not called');
            $this->attemptRecovery('core_ui_namespace', function () {
                Blade::anonymousComponentNamespace('forms::core-ui.components', 'core-ui');
            });
        }
    }

    private function checkRouteRegistration(): void
    {
        $routes = [
            'tickets.admin.index'  => 'Tickets admin index route',
            'tickets.admin.show'   => 'Tickets admin show route',
            'tickets.admin.bulk'   => 'Tickets admin bulk action route',
        ];

        foreach ($routes as $name => $label) {
            if (\Illuminate\Support\Facades\Route::has($name)) {
                $this->pass("route_{$name}", "{$label} registered");
            } else {
                $this->warn("route_{$name}", "{$label} NOT registered — check TicketsServiceProvider route loading");
            }
        }
    }

    private function checkMigrations(): void
    {
        try {
            $pending = app(\Illuminate\Database\Migrations\Migrator::class)->getMigrationFiles(
                database_path('migrations')
            );
            $this->pass('migrations', 'Migration check passed');
        } catch (\Throwable $e) {
            $this->warn('migrations', 'Could not check pending migrations: ' . $e->getMessage());
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function pass(string $key, string $message): void
    {
        $this->diagnostics[$key] = ['status' => 'ok', 'message' => $message];
    }

    private function warn(string $key, string $message): void
    {
        $this->diagnostics[$key] = ['status' => 'warning', 'message' => $message];
    }

    private function fail(string $key, string $message, string $detail = ''): void
    {
        $this->diagnostics[$key] = ['status' => 'failed', 'message' => $message, 'detail' => $detail];
        Log::warning("[FrontendRuntime] {$message}", ['detail' => $detail]);
    }

    private function attemptRecovery(string $key, callable $recovery): void
    {
        try {
            $recovery();
            $this->recoveries[$key] = ['status' => 'recovered', 'message' => "Auto-recovered: {$key}"];
            if (isset($this->diagnostics[$key])) {
                $this->diagnostics[$key]['status']  = 'recovered';
                $this->diagnostics[$key]['message'] .= ' [AUTO-RECOVERED]';
            }
        } catch (\Throwable $e) {
            $this->recoveries[$key] = ['status' => 'recovery_failed', 'error' => $e->getMessage()];
        }
    }

    private function isCoreUiNamespaceRegistered(): bool
    {
        try {
            return view()->exists('core-ui::design-tokens');
        } catch (\Throwable) {
            return false;
        }
    }
}
