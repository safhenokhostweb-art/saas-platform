<?php
namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\Module;
use App\Models\ModuleLicense;
use App\Models\ModulePurchase;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;

/**
 * موتور لایسنس ماژول: صدور، اعطا، اعتبارسنجی، لغو.
 */
class ModuleLicenseService
{
    /**
     * آیا سایت برای این ماژول لایسنس معتبر دارد؟
     */
    public function hasValidLicense(Site $site, int $moduleId): bool
    {
        $module = Module::find($moduleId);
        if (! $module) {
            return false;
        }
        if ($module->type === Module::TYPE_CORE) {
            return true;
        }
        if ($module->type !== Module::TYPE_PAID) {
            return false;
        }

        return ModuleLicense::query()
            ->where('site_id', $site->id)
            ->where('module_id', $moduleId)
            ->where('status', ModuleLicense::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereNull('revoked_at')
            ->exists();
    }

    /**
     * با slug ماژول.
     */
    public function hasValidLicenseBySlug(Site $site, string $moduleSlug): bool
    {
        $module = Module::where('slug', $moduleSlug)->first();
        return $module && $this->hasValidLicense($site, $module->id);
    }

    /**
     * لایسنس فعال (یا چند لایسنس) برای سایت و ماژول.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ModuleLicense>
     */
    public function getActiveLicenses(Site $site, int $moduleId)
    {
        return ModuleLicense::query()
            ->where('site_id', $site->id)
            ->where('module_id', $moduleId)
            ->where('status', ModuleLicense::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereNull('revoked_at')
            ->orderByDesc('expires_at')
            ->get();
    }

    /**
     * صدور لایسنس پس از پرداخت (از روی InvoiceItem).
     */
    public function createFromInvoiceItem(Site $site, int $moduleId, InvoiceItem $item, string $licenseType = ModuleLicense::TYPE_ONE_TIME, ?Carbon $expiresAt = null): ?ModuleLicense
    {
        $module = Module::where('type', Module::TYPE_PAID)->find($moduleId);
        if (! $module) {
            return null;
        }

        $purchase = ModulePurchase::where('site_id', $site->id)->where('module_id', $moduleId)->latest()->first();

        return ModuleLicense::create([
            'site_id' => $site->id,
            'module_id' => $moduleId,
            'license_type' => $licenseType,
            'purchased_at' => now(),
            'expires_at' => $expiresAt,
            'status' => ModuleLicense::STATUS_ACTIVE,
            'support_eligibility' => true,
            'invoice_id' => $item->invoice_id,
            'invoice_item_id' => $item->id,
            'module_purchase_id' => $purchase?->id,
            'granted_by' => null,
        ]);
    }

    /**
     * اعطای لایسنس توسط ادمین (تبلیغاتی / تست).
     */
    public function grantByAdmin(Site $site, int $moduleId, User $admin, ?Carbon $expiresAt = null): ?ModuleLicense
    {
        $module = Module::assignableToSite()->find($moduleId);
        if (! $module) {
            return null;
        }

        return ModuleLicense::create([
            'site_id' => $site->id,
            'module_id' => $moduleId,
            'license_type' => ModuleLicense::TYPE_ONE_TIME,
            'purchased_at' => now(),
            'expires_at' => $expiresAt,
            'status' => ModuleLicense::STATUS_ACTIVE,
            'support_eligibility' => false,
            'invoice_id' => null,
            'invoice_item_id' => null,
            'module_purchase_id' => null,
            'granted_by' => $admin->id,
        ]);
    }

    /**
     * لغو لایسنس.
     */
    public function revoke(ModuleLicense $license, ?User $revokedBy = null): void
    {
        $license->update([
            'status' => ModuleLicense::STATUS_REVOKED,
            'revoked_at' => now(),
        ]);
    }

    /**
     * شناسه ماژول‌هایی که سایت برای آن‌ها لایسنس معتبر دارد.
     *
     * @return array<int>
     */
    public function getLicensedModuleIdsForSite(Site $site): array
    {
        return ModuleLicense::query()
            ->where('site_id', $site->id)
            ->where('status', ModuleLicense::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereNull('revoked_at')
            ->distinct()
            ->pluck('module_id')
            ->values()
            ->all();
    }
}
