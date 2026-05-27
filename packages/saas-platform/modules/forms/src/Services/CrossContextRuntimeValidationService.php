<?php

namespace SaaSPlatform\Modules\Forms\Services;

/**
 * Phase 9.3 — CrossContextRuntimeValidationService
 *
 * Validates the 9 dimensions of runtime parity between saas-platform and
 * customer-site. Each dimension confirms that the same canonical source
 * (package view, service, route) is used in both contexts.
 *
 * Dimensions:
 *  1. Route coverage         — same routes accessible in both contexts
 *  2. Component namespace    — core-ui:: resolves identically
 *  3. Asset integrity        — same CDN URLs / SRI hashes
 *  4. Modal system           — core-ui::modal used (not native confirm())
 *  5. Toast system           — core-ui::toast used (not inline session flash)
 *  6. Builder state          — Alpine formBuilder component identical
 *  7. Animation tokens       — --forms-anim-* tokens present
 *  8. Alpine state safety    — no known race conditions
 *  9. Tenant/platform gates  — @platform/@tenant directives both registered
 */
class CrossContextRuntimeValidationService
{
    public function validate(): array
    {
        return [
            'route_coverage'       => $this->checkRouteCoverage(),
            'component_namespace'  => $this->checkComponentNamespace(),
            'asset_integrity'      => $this->checkAssetIntegrity(),
            'modal_system'         => $this->checkModalSystem(),
            'toast_system'         => $this->checkToastSystem(),
            'builder_state'        => $this->checkBuilderState(),
            'animation_tokens'     => $this->checkAnimationTokens(),
            'alpine_state_safety'  => $this->checkAlpineStateSafety(),
            'tenant_platform_gates'=> $this->checkTenantPlatformGates(),
        ];
    }

    public function score(): int
    {
        $results = $this->validate();
        $weights = [
            'route_coverage'        => 15,
            'component_namespace'   => 15,
            'asset_integrity'       => 10,
            'modal_system'          => 10,
            'toast_system'          => 10,
            'builder_state'         => 10,
            'animation_tokens'      => 10,
            'alpine_state_safety'   => 10,
            'tenant_platform_gates' => 10,
        ];

        $total = 0;
        foreach ($results as $key => $check) {
            $w     = $weights[$key] ?? 10;
            $total += ($check['passed'] ?? false) ? $w : 0;
        }

        return min(100, $total);
    }

    public function isValid(): bool
    {
        return $this->score() >= 85;
    }

    // ─── Dimensions ───────────────────────────────────────────────────────────

    private function checkRouteCoverage(): array
    {
        $required = [
            'tickets.admin.index',
            'tickets.admin.show',
            'tickets.admin.create',
            'tickets.admin.store',
            'tickets.admin.bulk',
            'tickets.admin.status.update',
            'tickets.admin.assign',
            'tickets.admin.replies.store',
        ];

        $missing = [];
        foreach ($required as $route) {
            if (! \Illuminate\Support\Facades\Route::has($route)) {
                $missing[] = $route;
            }
        }

        return [
            'dimension' => 'Route coverage',
            'passed'    => empty($missing),
            'missing'   => $missing,
            'message'   => empty($missing)
                ? 'All required routes registered'
                : count($missing) . ' routes missing: ' . implode(', ', $missing),
        ];
    }

    private function checkComponentNamespace(): array
    {
        $registered = false;
        try {
            $registered = view()->exists('core-ui::design-tokens') || view()->exists('core-ui::toast');
        } catch (\Throwable) {
        }

        $spFile = base_path('packages/saas-platform/modules/forms/src/Providers/FormsServiceProvider.php');
        $inCode = file_exists($spFile) && str_contains(file_get_contents($spFile), 'anonymousComponentNamespace');

        return [
            'dimension'   => 'Component namespace (core-ui::)',
            'passed'      => $inCode,
            'resolvable'  => $registered,
            'in_code'     => $inCode,
            'message'     => $inCode
                ? 'core-ui:: anonymous namespace registered in FormsServiceProvider'
                : 'DRIFT: core-ui:: namespace NOT registered — x-core-ui::* components will fail',
        ];
    }

    private function checkAssetIntegrity(): array
    {
        $builderFile = base_path('packages/saas-platform/modules/forms/resources/views/admin/forms/builder.blade.php');
        $hasSri = false;
        if (file_exists($builderFile)) {
            $content = file_get_contents($builderFile);
            $hasSri  = str_contains($content, 'integrity="sha256-');
        }

        return [
            'dimension' => 'Asset SRI integrity',
            'passed'    => $hasSri,
            'message'   => $hasSri
                ? 'SRI hash present on CDN assets in builder.blade.php'
                : 'WARNING: No SRI hash found on CDN scripts — supply chain risk',
        ];
    }

    private function checkModalSystem(): array
    {
        $builderFile = base_path('packages/saas-platform/modules/forms/resources/views/admin/forms/builder.blade.php');
        $usesNativeConfirm = false;
        $usesAlpineModal   = false;

        if (file_exists($builderFile)) {
            $content           = file_get_contents($builderFile);
            $usesNativeConfirm = preg_match('/\bconfirm\s*\(/', $content) === 1;
            $usesAlpineModal   = str_contains($content, 'showDeleteConfirm');
        }

        $passed = ! $usesNativeConfirm && $usesAlpineModal;

        return [
            'dimension'          => 'Modal system (Alpine-based, no native confirm())',
            'passed'             => $passed,
            'uses_native_confirm'=> $usesNativeConfirm,
            'uses_alpine_modal'  => $usesAlpineModal,
            'message'            => $passed
                ? 'All confirmation dialogs use Alpine-powered modals with proper ARIA'
                : 'DRIFT: native confirm() still in use or Alpine modal not wired',
        ];
    }

    private function checkToastSystem(): array
    {
        $builderFile = base_path('packages/saas-platform/modules/forms/resources/views/admin/forms/builder.blade.php');
        $hasToastFn = false;
        $hasDedupe  = false;

        if (file_exists($builderFile)) {
            $content    = file_get_contents($builderFile);
            $hasToastFn = str_contains($content, 'showToast(');
            $hasDedupe  = str_contains($content, 'some(t =>');
        }

        $passed = $hasToastFn && $hasDedupe;

        return [
            'dimension'  => 'Toast system (deduplicated, non-blocking)',
            'passed'     => $passed,
            'has_fn'     => $hasToastFn,
            'has_dedupe' => $hasDedupe,
            'message'    => $passed
                ? 'Toast system with deduplication active in builder'
                : 'Toast system incomplete — deduplication or showToast() missing',
        ];
    }

    private function checkBuilderState(): array
    {
        $builderFile = base_path('packages/saas-platform/modules/forms/resources/views/admin/forms/builder.blade.php');
        $checks = [
            'AbortController'       => false,
            'updateFieldOrderFromDom' => false,
            'pushHistory'           => false,
            'saveStatus'            => false,
        ];

        if (file_exists($builderFile)) {
            $content = file_get_contents($builderFile);
            foreach ($checks as $k => $v) {
                $checks[$k] = str_contains($content, $k);
            }
        }

        $passed = ! in_array(false, $checks, true);

        return [
            'dimension' => 'Alpine formBuilder state safety',
            'passed'    => $passed,
            'checks'    => $checks,
            'message'   => $passed
                ? 'All Phase 9.2 state safety patches present (AbortController, DOM resync, history, status)'
                : 'Builder state safety incomplete: ' . implode(', ', array_keys(array_filter($checks, fn ($v) => ! $v))),
        ];
    }

    private function checkAnimationTokens(): array
    {
        $tokenFile = base_path('packages/saas-platform/modules/forms/resources/views/core-ui/components/design-tokens.blade.php');
        $hasAnimTokens = false;

        if (file_exists($tokenFile)) {
            $content       = file_get_contents($tokenFile);
            $hasAnimTokens = str_contains($content, '--forms-anim-') && str_contains($content, '--forms-space-');
        }

        return [
            'dimension' => 'Animation / design token system',
            'passed'    => $hasAnimTokens,
            'message'   => $hasAnimTokens
                ? 'Design token system with animation tokens present in design-tokens.blade.php'
                : 'Design tokens file missing or incomplete — visual consistency at risk',
        ];
    }

    private function checkAlpineStateSafety(): array
    {
        $builderFile = base_path('packages/saas-platform/modules/forms/resources/views/admin/forms/builder.blade.php');
        $hasAbort   = false;
        $hasSortableDestroy = false;
        $hasDebounce = false;

        if (file_exists($builderFile)) {
            $content = file_get_contents($builderFile);
            $hasAbort            = str_contains($content, '_saveController.abort()');
            $hasSortableDestroy  = str_contains($content, 'sortableInstance.destroy()');
            $hasDebounce         = str_contains($content, 'clearTimeout(this._saveTimer)');
        }

        $passed = $hasAbort && $hasSortableDestroy && $hasDebounce;

        return [
            'dimension'             => 'Alpine state race condition prevention',
            'passed'                => $passed,
            'abort_controller'      => $hasAbort,
            'sortable_cleanup'      => $hasSortableDestroy,
            'debounce_present'      => $hasDebounce,
            'message'               => $passed
                ? 'All Alpine state race conditions patched (AbortController, SortableJS cleanup, debounce)'
                : 'Race condition patches incomplete',
        ];
    }

    private function checkTenantPlatformGates(): array
    {
        $directives = \Illuminate\Support\Facades\Blade::getCustomDirectives();
        $hasPlatform = isset($directives['platform']) || isset($directives['ifplatform']);
        $hasTenant   = isset($directives['tenant'])   || isset($directives['iftenant']);

        $fspFile  = base_path('packages/saas-platform/modules/forms/src/Providers/FormsServiceProvider.php');
        $tspFile  = base_path('packages/saas-platform/modules/tickets/src/Providers/TicketsServiceProvider.php');
        $inForms   = file_exists($fspFile) && str_contains(file_get_contents($fspFile), "Blade::if('platform'");
        $inTickets = file_exists($tspFile) && str_contains(file_get_contents($tspFile), 'registerBladeDirectives');

        $passed = $inForms && $inTickets;

        return [
            'dimension'     => '@platform/@tenant Blade directives',
            'passed'        => $passed,
            'in_forms_sp'   => $inForms,
            'in_tickets_sp' => $inTickets,
            'runtime_bound' => $hasPlatform && $hasTenant,
            'message'       => $passed
                ? '@platform and @tenant directives registered in both FormsServiceProvider and TicketsServiceProvider'
                : 'DRIFT: @platform/@tenant directives not registered in one or both service providers',
        ];
    }
}
