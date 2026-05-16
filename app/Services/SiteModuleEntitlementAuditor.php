<?php

namespace App\Services;

use App\Models\Module;
use App\Models\ModuleEntitlement;
use App\Models\ModuleLicense;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Wave 8.0c — هم‌ترازی append-only ledger با تغییرات دستی pivot سایت↔ماژول
 * (صفحهٔ «ماژول‌های سایت» و toggle سریع). بدون تغییر در gate زمان اجرا.
 */
class SiteModuleEntitlementAuditor
{
    public function __construct(
        protected EntitlementLedger $ledger,
    ) {}

    /**
     * پس از اعمال نهایی is_active روی site_modules برای یک ماژول قابل اختصاص دستی.
     */
    public function syncAfterPivotChange(Site $site, Module $module, bool $wasActive, bool $nowActive): void
    {
        if ($wasActive === $nowActive) {
            return;
        }

        try {
            if ($nowActive) {
                $this->grantForManualEnable($site, $module);

                return;
            }

            $this->ledger->revokeBySiteModule(
                (int) $site->id,
                (int) $module->id,
                Auth::id(),
                'site_modules: admin disabled module for tenant (manual assignment UI).'
            );
        } catch (\Throwable $e) {
            Log::warning('SiteModuleEntitlementAuditor sync failed', [
                'site_id' => $site->id,
                'module_id' => $module->id,
                'was_active' => $wasActive,
                'now_active' => $nowActive,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function grantForManualEnable(Site $site, Module $module): void
    {
        if ($module->isFreeAssignable()) {
            $this->ledger->grant($site, $module, ModuleEntitlement::SOURCE_FREE_ASSIGN, [
                'granted_by' => Auth::id(),
                'reason' => 'site_modules_manual_enable',
                'metadata' => ['path' => 'site_modules'],
            ]);

            return;
        }

        if ($module->isLicenseOnly()) {
            $license = ModuleLicense::query()
                ->where('site_id', $site->id)
                ->where('module_id', $module->id)
                ->where('status', ModuleLicense::STATUS_ACTIVE)
                ->whereNull('revoked_at')
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->orderByDesc('id')
                ->first();

            $this->ledger->grant($site, $module, ModuleEntitlement::SOURCE_LICENSE, [
                'granted_by' => Auth::id(),
                'module_license_id' => $license?->id,
                'reason' => 'site_modules_manual_enable',
                'metadata' => ['path' => 'site_modules'],
            ]);
        }
    }
}
