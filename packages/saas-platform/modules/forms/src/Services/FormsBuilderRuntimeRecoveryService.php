<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Phase 10.2.6 — Builder Runtime Recovery Service
 *
 * Detects and auto-recovers builder runtime failures:
 *  - Missing or blank builder view
 *  - Missing AbortController / debounce pattern
 *  - Missing Alpine x-cloak / x-data scope
 *  - Missing modal state variables
 *  - Broken route registration (triggers route:clear + cache:clear)
 *  - Missing CSS utility classes in customer-site
 */
class FormsBuilderRuntimeRecoveryService
{
    /** @var array<string, array{status: string, message: string, action?: string}> */
    private array $report = [];

    public function recover(bool $dryRun = false): array
    {
        $this->report = [];

        $this->recoverRouteCache($dryRun);
        $this->recoverBuilderView($dryRun);
        $this->recoverAlpineInitGuard($dryRun);
        $this->recoverAbortControllerPattern($dryRun);
        $this->recoverModalStateVars($dryRun);
        $this->recoverXCloak($dryRun);
        $this->recoverCustomerSiteCSS($dryRun);

        $recovered = count(array_filter($this->report, fn ($r) => $r['status'] === 'recovered'));
        $failed    = count(array_filter($this->report, fn ($r) => $r['status'] === 'failed'));
        $skipped   = count(array_filter($this->report, fn ($r) => $r['status'] === 'skipped'));
        $healthy   = count(array_filter($this->report, fn ($r) => $r['status'] === 'healthy'));

        return [
            'dry_run'   => $dryRun,
            'report'    => $this->report,
            'recovered' => $recovered,
            'failed'    => $failed,
            'skipped'   => $skipped,
            'healthy'   => $healthy,
            'success'   => $failed === 0,
        ];
    }

    // ─────────────────────── Recovery Actions ───────────────────────

    private function recoverRouteCache(bool $dryRun): void
    {
        $builderRouteExists = Route::has('admin.forms.builder');

        if ($builderRouteExists) {
            $this->record('route_cache', 'healthy', 'admin.forms.builder route is registered');
            return;
        }

        if ($dryRun) {
            $this->record('route_cache', 'skipped', 'Would run: route:clear + cache:clear', 'route:clear');
            return;
        }

        try {
            Artisan::call('route:clear');
            Artisan::call('cache:clear');
            // Re-check after clearing
            if (Route::has('admin.forms.builder')) {
                $this->record('route_cache', 'recovered', 'Routes re-registered after route:clear', 'route:clear + cache:clear');
            } else {
                $this->record('route_cache', 'failed', 'admin.forms.builder still missing after route:clear — check FormsServiceProvider');
            }
        } catch (\Throwable $e) {
            $this->record('route_cache', 'failed', 'route:clear threw: ' . $e->getMessage());
            Log::error('[BuilderRecovery] route:clear failed', ['error' => $e->getMessage()]);
        }
    }

    private function recoverBuilderView(bool $dryRun): void
    {
        $viewPath = $this->builderViewPath();

        if (! file_exists($viewPath)) {
            $this->record('builder_view', 'failed', 'Builder view missing at ' . $viewPath . ' — cannot auto-recover, check git');
            return;
        }

        $size = filesize($viewPath);
        if ($size < 1000) {
            $this->record('builder_view', 'failed', "Builder view exists but is suspiciously small ({$size} bytes) — may be truncated");
            return;
        }

        $this->record('builder_view', 'healthy', "Builder view present ({$size} bytes)");
    }

    private function recoverAlpineInitGuard(bool $dryRun): void
    {
        $viewPath = $this->builderViewPath();
        if (! file_exists($viewPath)) {
            $this->record('alpine_init_guard', 'skipped', 'Builder view missing — cannot inspect');
            return;
        }

        $content = file_get_contents($viewPath);
        $hasInit = str_contains($content, "alpine:init");

        if ($hasInit) {
            $this->record('alpine_init_guard', 'healthy', "alpine:init listener present in builder view");
            return;
        }

        // Cannot safely auto-patch the Alpine registration — structural rewrite required
        $this->record('alpine_init_guard', 'failed',
            'alpine:init listener missing — Alpine.data must be registered inside document.addEventListener("alpine:init") ' .
            'to avoid "formBuilder is not defined" race condition. Manual fix required.');
    }

    private function recoverAbortControllerPattern(bool $dryRun): void
    {
        $viewPath = $this->builderViewPath();
        if (! file_exists($viewPath)) {
            $this->record('abort_controller', 'skipped', 'Builder view missing');
            return;
        }

        $content = file_get_contents($viewPath);
        if (str_contains($content, '_saveController') && str_contains($content, 'new AbortController()')) {
            $this->record('abort_controller', 'healthy', 'AbortController pattern present');
            return;
        }

        $this->record('abort_controller', 'failed',
            'AbortController pattern missing — stale fetch requests can cause race conditions on rapid saves. Manual fix required.');
    }

    private function recoverModalStateVars(bool $dryRun): void
    {
        $viewPath = $this->builderViewPath();
        if (! file_exists($viewPath)) {
            $this->record('modal_state_vars', 'skipped', 'Builder view missing');
            return;
        }

        $content  = file_get_contents($viewPath);
        $required = ['showDeleteConfirm', 'showTemplateModal', 'showKeyboardHelp', 'showTemplateApplyConfirm'];
        $missing  = array_filter($required, fn ($v) => ! str_contains($content, $v));

        if (empty($missing)) {
            $this->record('modal_state_vars', 'healthy', 'All modal state variables present');
            return;
        }

        $this->record('modal_state_vars', 'failed',
            'Missing modal state variables: ' . implode(', ', $missing) . '. Manual fix required in builder.blade.php Alpine data init.');
    }

    private function recoverXCloak(bool $dryRun): void
    {
        // Check saas-platform app.css
        $saasCss = base_path('resources/css/app.css');
        if (file_exists($saasCss) && str_contains(file_get_contents($saasCss), '[x-cloak]')) {
            $this->record('x_cloak_saas', 'healthy', '[x-cloak] rule present in saas-platform app.css');
        } else {
            $this->record('x_cloak_saas', 'failed', '[x-cloak]{display:none!important} missing from saas-platform app.css — FOUC will occur');
        }

        // Check customer-site app.css
        $tenantCss = $this->customerSitePath('resources/css/app.css');
        if (! file_exists($tenantCss)) {
            $this->record('x_cloak_tenant', 'skipped', 'customer-site app.css not found');
            return;
        }

        if (str_contains(file_get_contents($tenantCss), '[x-cloak]')) {
            $this->record('x_cloak_tenant', 'healthy', '[x-cloak] rule present in customer-site app.css');
            return;
        }

        if ($dryRun) {
            $this->record('x_cloak_tenant', 'skipped', 'Would append [x-cloak]{display:none!important} to customer-site app.css');
            return;
        }

        try {
            file_put_contents($tenantCss, "\n[x-cloak]{display:none!important}\n", FILE_APPEND);
            $this->record('x_cloak_tenant', 'recovered', 'Appended [x-cloak] rule to customer-site app.css', 'file_append');
        } catch (\Throwable $e) {
            $this->record('x_cloak_tenant', 'failed', 'Could not write to customer-site app.css: ' . $e->getMessage());
        }
    }

    private function recoverCustomerSiteCSS(bool $dryRun): void
    {
        $tenantCss = $this->customerSitePath('resources/css/app.css');
        if (! file_exists($tenantCss)) {
            $this->record('customer_site_builder_css', 'skipped', 'customer-site app.css not found at expected path');
            return;
        }

        $content     = file_get_contents($tenantCss);
        $requiredIds = ['#forms-builder', '.builder-palette', '.builder-canvas', '.builder-inspector', '.canvas-grid'];
        $missing     = array_filter($requiredIds, fn ($c) => ! str_contains($content, $c));

        if (empty($missing)) {
            $this->record('customer_site_builder_css', 'healthy', 'All builder CSS classes present in customer-site app.css');
            return;
        }

        $this->record('customer_site_builder_css', 'failed',
            'Missing builder CSS in customer-site: ' . implode(', ', $missing) .
            '. Phase 10.2.6 CSS block must be present — see docs/phase-10.2.6/forms-builder-runtime-trace.md');
    }

    // ─────────────────────── Helpers ───────────────────────

    private function builderViewPath(): string
    {
        return __DIR__ . '/../../resources/views/admin/forms/builder.blade.php';
    }

    private function customerSitePath(string $relative): string
    {
        return base_path('../../customer-site/' . $relative);
    }

    private function record(string $key, string $status, string $message, string $action = ''): void
    {
        $this->report[$key] = array_filter([
            'status'  => $status,
            'message' => $message,
            'action'  => $action ?: null,
        ]);
    }
}
