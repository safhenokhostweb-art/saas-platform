<?php

namespace App\Services;

use App\Models\Module;
use App\Models\ModuleEntitlement;
use App\Models\ModuleLicense;
use App\Models\ModulePurchase;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;

/**
 * The one admin path that licenses a paid or license-only module (a "kit"
 * such as `solar_industry_kit`) to a site: ModuleLicense, the zero-amount
 * ModulePurchase, and the EntitlementLedger grant. Extracted unchanged from
 * ModuleLicenseController::grant() so the onboarding pipeline
 * (ApplySiteEntitlementsStage, ADR-088) licenses a kit exactly the way the
 * panel does, without a second copy of the sequence.
 *
 * Licensing does not activate the module: ModuleAssignmentService::
 * enableForSite() remains the only writer of the active site_modules pivot.
 */
class ModuleLicenseGrantService
{
    public const GRANTED = 'granted';

    public const ALREADY_LICENSED = 'already_licensed';

    public const INVALID_MODULE = 'invalid_module';

    public function __construct(
        private readonly ModuleLicenseService $licenses,
        private readonly ModulePurchaseService $purchases,
        private readonly EntitlementLedger $ledger,
    ) {}

    /** @return self::GRANTED|self::ALREADY_LICENSED|self::INVALID_MODULE */
    public function grant(Site $site, int $moduleId, User $admin, ?Carbon $expiresAt = null, string $path = 'admin_module_license_grant'): string
    {
        $module = Module::assignableToSite()->find($moduleId);
        if (! $module || $module->type !== Module::TYPE_PAID) {
            return self::INVALID_MODULE;
        }

        if ($this->licenses->hasValidLicense($site, $module->id)) {
            return self::ALREADY_LICENSED;
        }

        $this->licenses->grantByAdmin($site, $module->id, $admin, $expiresAt);
        $this->purchases->grantByAdmin($site, $module->id, $admin);

        // Wave 8.0b — ledger grant (idempotent؛ activity_logs فقط از EntitlementLedger)
        try {
            $license = ModuleLicense::query()
                ->where('site_id', $site->id)
                ->where('module_id', $module->id)
                ->where('status', ModuleLicense::STATUS_ACTIVE)
                ->whereNull('revoked_at')
                ->orderByDesc('id')
                ->first();
            $purchase = ModulePurchase::query()
                ->where('site_id', $site->id)
                ->where('module_id', $module->id)
                ->orderByDesc('id')
                ->first();
            $this->ledger->grant($site, $module, ModuleEntitlement::SOURCE_ADMIN_GRANT, [
                'granted_by' => $admin->id,
                'module_license_id' => $license?->id,
                'module_purchase_id' => $purchase?->id,
                'metadata' => ['path' => $path],
            ]);
        } catch (\Throwable) {
            // best-effort: اعطای لایسنس اصلی نباید به خاطر ledger شکست بخورد
        }

        return self::GRANTED;
    }
}
