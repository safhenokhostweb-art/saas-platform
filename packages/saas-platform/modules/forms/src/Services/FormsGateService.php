<?php

namespace SaaSPlatform\Modules\Forms\Services;

use App\Models\Site;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Log;

/**
 * TASK 3 — Tenant Feature Gates
 *
 * Centralized tenant/module access checks for the Forms module.
 * Ensures that routes, menus, controllers, widgets, and API endpoints
 * all enforce module entitlement consistently.
 *
 * This service provides a single point of truth for all gating decisions.
 * No controller, view, or route should bypass this service.
 */
class FormsGateService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected FormsLicensingService $licensingService
    ) {}

    /**
     * Check if the current tenant can access Forms.
     * This is the PRIMARY gate method - all routes/controllers should use this.
     *
     * @return array{granted: bool, reason: ?string}
     */
    public function authorize(): array
    {
        $site = $this->tenantContext->site();

        if (!$site) {
            Log::warning('forms.gate.no_tenant', [
                'ip' => request()->ip(),
                'url' => request()->fullUrl(),
            ]);
            return [
                'granted' => false,
                'reason' => 'تنانت (سایت) شناسایی نشد.',
            ];
        }

        return $this->authorizeForSite($site);
    }

    /**
     * Check if a specific site can access Forms.
     */
    public function authorizeForSite(Site $site): array
    {
        $result = $this->licensingService->checkEntitlement($site);

        if (!$result['allowed']) {
            $this->licensingService->logDenial(
                $site,
                'gate:authorizeForSite',
                $result['reason'] ?? 'دسترسی مجاز نیست.'
            );
        }

        return [
            'granted' => $result['allowed'],
            'reason' => $result['reason'],
        ];
    }

    /**
     * Quick boolean check for Blade views and menu rendering.
     */
    public function canAccess(): bool
    {
        $result = $this->authorize();
        return $result['granted'];
    }

    /**
     * Check if a specific feature is enabled for the current tenant.
     * Features are defined in the module manifest.
     */
    public function featureEnabled(string $featureId): bool
    {
        $manifest = config('module-manifest-forms.features', []);
        foreach ($manifest as $feature) {
            if (($feature['id'] ?? '') === $featureId) {
                return ($feature['status'] ?? '') === 'active' && ($feature['implemented'] ?? false) === true;
            }
        }
        return false;
    }

    /**
     * Get all enabled features for the current tenant.
     *
     * @return array<int, array{id: string, title: string}>
     */
    public function getEnabledFeatures(): array
    {
        if (!$this->canAccess()) {
            return [];
        }

        $manifest = config('module-manifest-forms.features', []);
        $enabled = [];

        foreach ($manifest as $feature) {
            if (($feature['status'] ?? '') === 'active' && ($feature['implemented'] ?? false) === true) {
                $enabled[] = [
                    'id' => $feature['id'] ?? '',
                    'title' => $feature['title'] ?? '',
                ];
            }
        }

        return $enabled;
    }

    /**
     * Middleware-compatible check. Throws 403 if not granted.
     */
    public function authorizeOrFail(): void
    {
        $result = $this->authorize();

        if (!$result['granted']) {
            abort(403, $result['reason'] ?? 'دسترسی به ماژول فرم‌ها مجاز نیست.');
        }
    }
}
