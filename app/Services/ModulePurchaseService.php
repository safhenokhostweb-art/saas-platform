<?php
namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\Module;
use App\Models\ModulePurchase;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * سرویس خرید و مالکیت ماژول.
 * ثبت خرید، اعطای دستی، بررسی مالکیت.
 */
class ModulePurchaseService
{
    /**
     * ثبت خرید ماژول (پس از پرداخت) و صدور لایسنس.
     */
    public function recordPurchaseFromInvoice(Site $site, int $moduleId, InvoiceItem $invoiceItem): ?ModulePurchase
    {
        $module = Module::where('type', Module::TYPE_PAID)->find($moduleId);
        if (! $module) {
            Log::error('Module purchase record skipped: module not found or not type=paid', [
                'site_id' => $site->id,
                'module_id' => $moduleId,
                'invoice_id' => $invoiceItem->invoice_id,
                'invoice_item_id' => $invoiceItem->id,
            ]);
            return null;
        }

        $purchase = ModulePurchase::updateOrCreate(
            [
                'site_id' => $site->id,
                'module_id' => $moduleId,
            ],
            [
                'invoice_id' => $invoiceItem->invoice_id,
                'invoice_item_id' => $invoiceItem->id,
                'amount_irr' => $invoiceItem->price_irr * $invoiceItem->quantity,
                'granted_by' => null,
                'purchased_at' => now(),
            ]
        );

        $licenseService = app(\App\Services\ModuleLicenseService::class);
        if (! $licenseService->hasValidLicense($site, $moduleId)) {
            $licenseService->createFromInvoiceItem($site, $moduleId, $invoiceItem);
        }

        return $purchase;
    }

    /**
     * اعطای دستی ماژول توسط ادمین (بدون پرداخت) + صدور لایسنس تبلیغاتی.
     */
    public function grantByAdmin(Site $site, int $moduleId, User $admin): ?ModulePurchase
    {
        $module = Module::assignableToSite()->find($moduleId);
        if (! $module) {
            return null;
        }

        $purchase = ModulePurchase::updateOrCreate(
            [
                'site_id' => $site->id,
                'module_id' => $moduleId,
            ],
            [
                'invoice_id' => null,
                'invoice_item_id' => null,
                'amount_irr' => 0,
                'granted_by' => $admin->id,
                'purchased_at' => now(),
            ]
        );

        $licenseService = app(\App\Services\ModuleLicenseService::class);
        if (! $licenseService->hasValidLicense($site, $moduleId)) {
            $licenseService->grantByAdmin($site, $moduleId, $admin);
        }

        return $purchase;
    }

    /**
     * آیا سایت مالک/دارای لایسنس معتبر ماژول است؟ (خرید یا اعطا)
     */
    public function hasOwnership(Site $site, string $moduleSlug): bool
    {
        $module = Module::where('slug', $moduleSlug)->first();
        if (! $module) {
            return false;
        }
        if ($module->type === Module::TYPE_CORE) {
            return true;
        }
        if ($module->type === Module::TYPE_PAID) {
            return app(\App\Services\ModuleLicenseService::class)->hasValidLicenseBySlug($site, $moduleSlug);
        }
        return false;
    }

    /**
     * آیا سایت مالک ماژول است؟ (با module_id)
     */
    public function hasOwnershipById(Site $site, int $moduleId): bool
    {
        $module = Module::find($moduleId);
        if (! $module) {
            return false;
        }
        return $this->hasOwnership($site, $module->slug);
    }

    /**
     * slug ماژول‌های دارای لایسنس معتبر برای سایت.
     *
     * @return array<string>
     */
    public function getPurchasedSlugsForSite(Site $site): array
    {
        $licensedIds = app(\App\Services\ModuleLicenseService::class)->getLicensedModuleIdsForSite($site);
        return Module::whereIn('id', $licensedIds)->pluck('slug')->values()->all();
    }
}
