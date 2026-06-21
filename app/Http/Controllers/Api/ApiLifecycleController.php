<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\Lifecycle\SiteLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Tenant-facing lifecycle API.
 * All routes protected by tenant.api middleware — Site resolved from license key.
 */
class ApiLifecycleController extends Controller
{
    public function __construct(private SiteLifecycleService $lifecycle) {}

    /**
     * POST /api/lifecycle/reactivate
     *
     * Called by customer-site after subscription payment succeeds.
     * If site is suspended/grace-period, transitions it back to active.
     * Idempotent — safe to call even if already active.
     */
    public function reactivate(Request $request): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        if (! $site) {
            return response()->json(['success' => false, 'error' => 'site_not_found'], 404);
        }

        $currentStatus = $site->getLifecycleStatus();

        if ($currentStatus === Site::LIFECYCLE_ACTIVE) {
            return response()->json([
                'success' => true,
                'status'  => 'active',
                'message' => 'سایت در حال حاضر فعال است.',
            ]);
        }

        // Only non-deleted sites can be reactivated via subscription renewal
        if ($currentStatus === Site::LIFECYCLE_DELETED) {
            return response()->json([
                'success' => false,
                'error'   => 'site_deleted',
                'message' => 'این سایت حذف شده و قابل بازگردانی از طریق اشتراک نیست.',
            ], 403);
        }

        try {
            $this->lifecycle->activate($site, 'تمدید اشتراک — فعال‌سازی خودکار');

            Log::info('ApiLifecycleController: site reactivated via subscription renewal', [
                'site_id'        => $site->id,
                'from_status'    => $currentStatus,
            ]);

            return response()->json([
                'success' => true,
                'status'  => 'active',
                'message' => 'سایت با موفقیت فعال‌سازی مجدد شد.',
            ]);
        } catch (\Throwable $e) {
            Log::error('ApiLifecycleController: reactivate failed', [
                'site_id' => $site->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'reactivation_failed',
                'message' => 'خطا در فعال‌سازی سایت. لطفاً با پشتیبانی تماس بگیرید.',
            ], 500);
        }
    }

    /**
     * GET /api/lifecycle/status
     *
     * Returns current lifecycle status and restore fee (if applicable).
     */
    public function status(Request $request): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        if (! $site) {
            return response()->json(['success' => false, 'error' => 'site_not_found'], 404);
        }

        $reactivationFee = (int) config('lifecycle.reactivation_fee', 0);
        $restoreFee      = (int) config('lifecycle.archive_restore_fee', 0);
        $storageFee      = (int) config('lifecycle.storage_retention_fee', 0);
        $totalFee        = $reactivationFee + $restoreFee + $storageFee;

        return response()->json([
            'success'       => true,
            'status'        => $site->getLifecycleStatus(),
            'is_active'     => $site->getLifecycleStatus() === Site::LIFECYCLE_ACTIVE,
            'suspended_at'  => $site->suspended_at?->toIso8601String(),
            'archived_at'   => $site->archived_at?->toIso8601String(),
            'restore_fee'   => $totalFee,
            'fee_breakdown' => [
                'reactivation' => $reactivationFee,
                'restore'      => $restoreFee,
                'storage'      => $storageFee,
            ],
        ]);
    }

    /**
     * POST /api/lifecycle/request-restore
     *
     * Customer requests site restoration — creates SiteLifecycleInvoice.
     * Returns invoice details for payment.
     */
    public function requestRestore(Request $request): JsonResponse
    {
        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        if (! $site) {
            return response()->json(['success' => false, 'error' => 'site_not_found'], 404);
        }

        $currentStatus = $site->getLifecycleStatus();

        if ($currentStatus === Site::LIFECYCLE_ACTIVE) {
            return response()->json([
                'success' => true,
                'message' => 'سایت در حال حاضر فعال است.',
                'status'  => 'active',
            ]);
        }

        if ($currentStatus === Site::LIFECYCLE_DELETED) {
            return response()->json([
                'success' => false,
                'error'   => 'site_deleted',
                'message' => 'سایت حذف شده است.',
            ], 403);
        }

        try {
            $invoice = $this->lifecycle->requestRestore($site);

            Log::info('ApiLifecycleController: restore requested', [
                'site_id'        => $site->id,
                'invoice_number' => $invoice->invoice_number,
                'amount'         => $invoice->amount,
            ]);

            return response()->json([
                'success'        => true,
                'invoice_number' => $invoice->invoice_number,
                'amount'         => $invoice->amount,
                'due_at'         => $invoice->due_at?->toIso8601String(),
                'line_items'     => $invoice->line_items ?? [],
                'is_free'        => $invoice->amount == 0,
            ]);
        } catch (\Throwable $e) {
            Log::error('ApiLifecycleController: requestRestore failed', [
                'site_id' => $site->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'request_failed',
                'message' => 'خطا در ثبت درخواست بازگردانی.',
            ], 500);
        }
    }

    /**
     * POST /api/lifecycle/confirm-restore
     *
     * Called after payment is verified in customer-site.
     * Marks the lifecycle invoice paid and restores the site.
     */
    public function confirmRestore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'invoice_number' => 'required|string',
            'payment_ref'    => 'required|string',
        ]);

        /** @var Site $site */
        $site = $request->attributes->get('tenant_site');

        if (! $site) {
            return response()->json(['success' => false, 'error' => 'site_not_found'], 404);
        }

        $invoice = \App\Models\SiteLifecycleInvoice::where('site_id', $site->id)
            ->where('invoice_number', $validated['invoice_number'])
            ->first();

        if (! $invoice) {
            return response()->json([
                'success' => false,
                'error'   => 'invoice_not_found',
                'message' => 'فاکتور یافت نشد.',
            ], 404);
        }

        if ($invoice->status === \App\Models\SiteLifecycleInvoice::STATUS_PAID) {
            // Already paid — just ensure site is active
            if ($site->getLifecycleStatus() !== Site::LIFECYCLE_ACTIVE) {
                $this->lifecycle->restore($site, 'بازگردانی — فاکتور قبلاً پرداخت شده');
            }
            return response()->json(['success' => true, 'message' => 'سایت فعال است.']);
        }

        try {
            $this->lifecycle->markInvoicePaid($invoice, $validated['payment_ref']);
            $this->lifecycle->restore($site, 'بازگردانی — پرداخت تأیید شد');

            Log::info('ApiLifecycleController: restore confirmed via payment', [
                'site_id'     => $site->id,
                'invoice'     => $invoice->invoice_number,
                'payment_ref' => $validated['payment_ref'],
            ]);

            return response()->json([
                'success' => true,
                'status'  => 'active',
                'message' => 'سایت با موفقیت بازگردانی شد.',
            ]);
        } catch (\Throwable $e) {
            Log::error('ApiLifecycleController: confirmRestore failed', [
                'site_id' => $site->id,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'restore_failed',
                'message' => 'خطا در بازگردانی سایت.',
            ], 500);
        }
    }
}
