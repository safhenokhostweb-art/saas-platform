<?php

namespace App\Services\ModuleEcosystem;

use App\Models\Module;
use App\Models\ModuleEntitlement;
use App\Models\ModuleLicense;
use App\Models\PlatformLicense;
use App\Models\Site;
use App\Services\ModuleEcosystem\Contracts\EntitlementResolverInterface;
use App\Services\ModuleEcosystem\Dto\EntitlementState;
use App\Services\ModuleLicenseService;

/**
 * Read-only. Answers "is this site allowed to use this module?" by
 * combining the site-level PlatformLicense, the per-module ModuleLicense
 * (via the existing ModuleLicenseService::hasValidLicense()), and the
 * ModuleEntitlement audit ledger.
 *
 * Delegates license validity to ModuleLicenseService — does not
 * re-implement it. Never writes site_modules.is_active, never issues a
 * license, never records a purchase, never mutates the ledger.
 */
class EntitlementResolver implements EntitlementResolverInterface
{
    public function __construct(
        protected ModuleLicenseService $moduleLicenseService,
    ) {}

    public function resolve(Site $site, Module $module): EntitlementState
    {
        $platformLicenseState = $this->fromPlatformLicense($site);
        if ($platformLicenseState !== null) {
            return $platformLicenseState;
        }

        if ($module->isCore()) {
            return new EntitlementState(allowed: true);
        }

        if ($module->isFreeAssignable()) {
            return $this->fromEntitlementLedger($site, $module) ?? new EntitlementState(allowed: true);
        }

        if ($module->isLicenseOnly() || $module->isPaidMarketplace()) {
            return $this->resolveViaModuleLicense($site, $module);
        }

        return new EntitlementState(allowed: false, reason: 'not_entitled');
    }

    /**
     * Site-wide gate. A revoked or expired PlatformLicense denies every
     * module regardless of per-module state. Returns null when there is
     * no PlatformLicense row (not every site has one) or it is valid —
     * in both cases the caller falls through to per-module resolution.
     */
    protected function fromPlatformLicense(Site $site): ?EntitlementState
    {
        $license = PlatformLicense::where('site_id', $site->id)->latest('id')->first();
        if ($license === null) {
            return null;
        }
        if ($license->isRevoked()) {
            return new EntitlementState(allowed: false, reason: 'revoked');
        }
        if ($license->isExpired()) {
            return new EntitlementState(allowed: false, reason: 'license_expired');
        }
        return null;
    }

    /**
     * Gate for license_only / paid_marketplace modules. Reuses
     * ModuleLicenseService::hasValidLicense() for the Allowed decision;
     * only re-reads the ModuleLicense row (via its own isRevoked()/
     * isExpired() methods) to classify the Denied reason.
     */
    protected function resolveViaModuleLicense(Site $site, Module $module): EntitlementState
    {
        if ($this->moduleLicenseService->hasValidLicense($site, $module->id)) {
            return new EntitlementState(allowed: true);
        }

        $license = ModuleLicense::where('site_id', $site->id)
            ->where('module_id', $module->id)
            ->latest('id')
            ->first();

        if ($license !== null) {
            if ($license->isRevoked()) {
                return new EntitlementState(allowed: false, reason: 'revoked');
            }
            if ($license->isExpired()) {
                return new EntitlementState(allowed: false, reason: 'license_expired');
            }
        }

        return $this->fromEntitlementLedger($site, $module)
            ?? new EntitlementState(allowed: false, reason: 'no_purchase');
    }

    /**
     * Ledger is consulted, never the sole/final authority: it only fills
     * in an answer when there is no ModuleLicense row to decide from
     * (e.g. free_assign / admin_grant paths that never create a license
     * row), and a revoked ledger entry always denies.
     */
    protected function fromEntitlementLedger(Site $site, Module $module): ?EntitlementState
    {
        $entitlement = ModuleEntitlement::forSite($site->id)
            ->forModule($module->id)
            ->latest('id')
            ->first();

        if ($entitlement === null) {
            return null;
        }

        if ($entitlement->isRevoked()) {
            return new EntitlementState(allowed: false, reason: 'revoked');
        }

        if ($entitlement->isActive()) {
            return new EntitlementState(allowed: true);
        }

        return new EntitlementState(allowed: false, reason: 'not_entitled');
    }
}
