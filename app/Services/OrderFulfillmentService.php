<?php
namespace App\Services;

use App\Mail\LicenseIssuedMail;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use App\Models\Product;
use App\Models\ResellerCommission;
use App\Models\Site;
use App\Services\ModulePurchaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * پس از تأیید پرداخت: صدور لایسنس، الحاق ماژول، تمدید پشتیبانی.
 */
class OrderFulfillmentService
{
    public function __construct(
        protected LicenseServerService $licenseService
    ) {}

    public function fulfill(Invoice $invoice): void
    {
        if (!$invoice->isPaid()) {
            return;
        }

        foreach ($invoice->items as $item) {
            $this->fulfillItem($item);
        }

        $this->recordResellerCommission($invoice);
    }

    protected function recordResellerCommission(Invoice $invoice): void
    {
        $customer = $invoice->customer;
        if (!$customer->reseller_id) {
            return;
        }
        $reseller = $customer->reseller;
        if (!$reseller || (float) $reseller->commission_rate <= 0) {
            return;
        }
        $amount = (int) round($invoice->amount_irr * (float) $reseller->commission_rate / 100);
        if ($amount <= 0) {
            return;
        }
        ResellerCommission::create([
            'reseller_id' => $reseller->id,
            'invoice_id' => $invoice->id,
            'amount_irr' => $amount,
            'status' => ResellerCommission::STATUS_PENDING,
        ]);
    }

    protected function fulfillItem(InvoiceItem $item): void
    {
        $product = $item->product;
        $site = $item->site;

        switch ($product->type) {
            case Product::TYPE_LICENSE_TEMPLATE:
                $this->fulfillLicenseTemplate($item, $site);
                break;
            case Product::TYPE_MODULE_ADDON:
                $this->fulfillModuleAddon($item, $site);
                break;
            case Product::TYPE_SUPPORT_PLAN:
                $this->fulfillSupportPlan($item, $site);
                break;
            case Product::TYPE_MOTION_ASSET:
                $this->fulfillMotionAsset($item, $site);
                break;
        }
    }

    /**
     * Phase X — bespoke Motion commercialization. Records the purchase
     * ONLY. Deliberately does not create a MotionBespokeAssignment, does
     * not touch site_modules, and does not call ModuleAssignmentService —
     * per the task's explicit rule that a paid-for custom Motion must
     * never be automatically activated; assignment stays a separate,
     * deliberate Platform-Admin action (MotionBespokeAssignmentService).
     */
    protected function fulfillMotionAsset(InvoiceItem $item, ?Site $site): void
    {
        if (! $site) {
            return;
        }

        $commission = \App\Modules\MotionGraphics\Models\MotionBespokeCommission::query()
            ->where('product_id', $item->product_id)
            ->first();

        if ($commission === null || (int) $commission->site_id !== (int) $site->id) {
            Log::error('Motion asset fulfillment skipped: no matching commission for this site', [
                'invoice_item_id' => $item->id,
                'invoice_id' => $item->invoice_id,
                'site_id' => $item->site_id,
                'product_id' => $item->product_id,
            ]);
            return;
        }

        \App\Modules\MotionGraphics\Models\MotionBespokePurchase::query()->create([
            'commission_id' => $commission->id,
            'site_id' => $site->id,
            'invoice_id' => $item->invoice_id,
            'invoice_item_id' => $item->id,
            'amount_irr' => $item->price_irr * $item->quantity,
            'purchased_at' => now(),
        ]);

        Log::info('موشن اختصاصی خریداری شد (بدون فعال‌سازی خودکار)', [
            'site_id' => $site->id,
            'commission_id' => $commission->id,
            'invoice_id' => $item->invoice_id,
            'invoice_item_id' => $item->id,
        ]);
    }

    protected function fulfillLicenseTemplate(InvoiceItem $item, ?Site $site): void
    {
        if (!$site) {
            return;
        }
        $key = $this->licenseService->issue(
            $site,
            $site->domain,
            null, // لایسنس مادام‌العمر (بدون انقضا)
            $item->product->sku ?? 'site_template'
        );
        $platformLicense = $site->platformLicenses()->latest()->first();
        if ($platformLicense) {
            $item->update(['platform_license_id' => $platformLicense->id]);
        }

        // پشتیبانی رایگان برای مشتریان جدید: ۱۵ تا ۳۰ روز (قابل تنظیم در config)
        $freeDays = config('services.free_support_days_for_new_license', 30);
        if ($freeDays > 0) {
            $currentSupportEnd = $site->support_expires_at;
            $isNewOrExpired = $currentSupportEnd === null || $currentSupportEnd->isPast();
            if ($isNewOrExpired) {
                $newEndsAt = now()->addDays($freeDays);
                $site->update(['support_expires_at' => $newEndsAt]);
            }
        }

        $this->sendLicenseEmail($item->invoice, $key, $site);
    }

    protected function fulfillModuleAddon(InvoiceItem $item, ?Site $site): void
    {
        if (! $site || ! $item->product->module_id) {
            Log::error('Module addon fulfillment skipped: missing site or module_id on invoice item', [
                'invoice_item_id' => $item->id,
                'invoice_id' => $item->invoice_id,
                'site_id' => $item->site_id,
                'product_id' => $item->product_id,
                'module_id' => $item->product->module_id ?? null,
            ]);
            return;
        }
        $moduleId = $item->product->module_id;
        $module = Module::find($moduleId);

        // Phase 17-D — Part 5: previously a direct syncWithoutDetaching(),
        // which granted entitlement without ever attempting to deploy the
        // module's code — a paying customer could end up with is_active=true
        // and no working module. activatePaidPurchase() still guarantees
        // is_active=true unconditionally (payment already happened; access
        // must never hinge on an infra hiccup) but also runs the real
        // CustomerInstallation Pipeline dispatch, so deployment_status
        // honestly reflects whether the code is actually on its way.
        $activation = app(\App\Services\ModuleAssignmentService::class)->activatePaidPurchase($site, $moduleId);
        if (! $activation['success']) {
            Log::warning('Module addon fulfillment: deployment dispatch failed (entitlement still granted)', [
                'site_id' => $site->id,
                'module_id' => $moduleId,
                'invoice_id' => $item->invoice_id,
                'error' => $activation['error'] ?? null,
            ]);
        }

        $purchase = app(ModulePurchaseService::class)->recordPurchaseFromInvoice($site, $moduleId, $item);

        // Wave 5.1 / P1-1 — record ledger entry (best-effort; ledger has its
        // own try/catch and Schema guards, so a missing table or transient
        // failure can never break the payment flow).
        if ($module !== null) {
            try {
                app(\App\Services\EntitlementLedger::class)->grant(
                    $site,
                    $module,
                    \App\Models\ModuleEntitlement::SOURCE_PURCHASE,
                    [
                        'granted_by' => null,
                        'invoice_id' => $item->invoice_id,
                        'invoice_item_id' => $item->id,
                        'module_purchase_id' => $purchase?->id,
                        'metadata' => [
                            'product_sku' => $item->product->sku ?? null,
                            'amount_irr' => $item->price_irr * $item->quantity,
                        ],
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('EntitlementLedger grant failed in fulfillModuleAddon', [
                    'site_id' => $site->id,
                    'module_id' => $moduleId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('ماژول پس از پرداخت فعال شد', [
            'site_id' => $site->id,
            'module_id' => $moduleId,
            'invoice_id' => $item->invoice_id,
            'invoice_item_id' => $item->id,
        ]);

        // Wave 8.0b: audit فقط از EntitlementLedger::grant → activity_logs
        // (`entitlement_granted`)؛ از ثبت تکراری `module.activated` خودداری می‌شود.

        $this->notifyTenantModuleActivated($site, $module, $item->invoice);
    }

    /**
     * اطلاع‌رسانی به tenant پس از فعال‌سازی ماژول (ایمیل / اعلان).
     */
    protected function notifyTenantModuleActivated(Site $site, ?Module $module, Invoice $invoice): void
    {
        // Email notifications removed — system uses SMS/mobile only
        return;
        try {
            Mail::to($email)->send(new \App\Mail\ModuleActivatedMail(
                siteTitle: $site->title ?? $site->domain,
                moduleTitle: $module->title,
                domain: $site->domain
            ));
        } catch (\Throwable $e) {
            Log::warning('ارسال ایمیل فعال‌سازی ماژول ناموفق', ['site_id' => $site->id, 'error' => $e->getMessage()]);
        }
    }

    protected function fulfillSupportPlan(InvoiceItem $item, ?Site $site): void
    {
        if (!$site || !$item->product->support_plan_id) {
            return;
        }
        $plan = $item->product->supportPlan;
        $durationDays = $plan->duration_days ?? 30;

        $hasActiveSupport = $site->support_expires_at && $site->support_expires_at->isFuture();
        $newEndsAt = $hasActiveSupport
            ? $site->support_expires_at->addDays($durationDays)
            : now()->addDays($durationDays);

        $updateData = ['support_expires_at' => $newEndsAt];

        // P1-6: اگر ستون support_starts_at موجود است و هنوز ثبت نشده،
        // (یا support قبلی منقضی شده) شروع پشتیبانی فعلی را ثبت کن.
        // در تمدید پلان فعال، support_starts_at دست‌نخورده باقی می‌ماند.
        if (\Illuminate\Support\Facades\Schema::hasColumn('sites', 'support_starts_at')) {
            $existingStart = $site->support_starts_at ?? null;
            if (!$hasActiveSupport || empty($existingStart)) {
                $updateData['support_starts_at'] = now();
            }
        }

        $site->update($updateData);

        DB::table('site_support_subscriptions')->insert([
            'site_id' => $site->id,
            'support_plan_id' => $plan->id,
            'total_price' => $item->price_irr * $item->quantity,
            'starts_at' => now(),
            'ends_at' => $newEndsAt,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function sendLicenseEmail(Invoice $invoice, string $licenseKey, Site $site): void
    {
        // Email notifications removed — system uses SMS/mobile only
        return;

        $platformLicense = $site->platformLicenses()->latest()->first();
        $expiresAt = $platformLicense?->expires_at
            ? \App\Helpers\Jalali::formatFa($platformLicense->expires_at, 'Y/m/d')
            : null;

        try {
            Mail::to($email)->send(new LicenseIssuedMail(
                licenseKey: $licenseKey,
                customerName: $customer->name ?: 'مشتری',
                siteTitle: $site->title ?? 'سایت',
                domain: $site->domain,
                expiresAt: $expiresAt
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ارسال ایمیل لایسنس ناموفق', [
                'invoice_id' => $invoice->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
