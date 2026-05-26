<?php

namespace SaaSPlatform\Modules\Forms\Services;

use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Contracts\Platform\SiteModelInterface;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;

class FormsGateService
{
    public function __construct(
        protected TenantContextContract $tenantContext,
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

        if (!$site instanceof SiteModelInterface) {
            Log::warning('forms.gate.no_tenant');
            return ['granted' => false, 'reason' => 'تنانت (سایت) شناسایی نشد.'];
        }

        return $this->authorizeForSite($site);
    }

    public function authorizeForSite(SiteModelInterface $site): array
    {
        $result = $this->licensingService->checkEntitlement($site);

        if (!$result['allowed']) {
            $this->licensingService->logDenial(
                $site,
                'gate:authorizeForSite',
                $result['reason'] ?? 'دسترسی مجاز نیست.'
            );
        }

        return ['granted' => $result['allowed'], 'reason' => $result['reason']];
    }

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
