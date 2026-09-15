<?php

namespace App\Observers;

use App\Models\ThemePurchaseOrder;
use App\Services\AdminNotificationService;
use App\Services\Finance\FinanceLedgerService;
use App\Services\ThemeBuilder\ThemeInstallGateway;
use Illuminate\Support\Facades\Log;

class ThemePurchaseOrderObserver
{
    public function updated(ThemePurchaseOrder $order): void
    {
        if (!$order->wasChanged('status') || $order->status !== 'paid') {
            return;
        }

        // Auto-record in finance ledger
        try {
            app(FinanceLedgerService::class)->fromThemePurchase($order);
        } catch (\Throwable $e) {
            Log::warning('FinanceLedger: failed to record theme purchase', ['order' => $order->id, 'err' => $e->getMessage()]);
        }

        // Send admin notification
        AdminNotificationService::send(
            type: 'success',
            title: 'خرید قالب انجام شد',
            body: "قالب «{$order->theme->name}» توسط سایت {$order->site_id} خریداری شد.",
            link: "/admin/theme-marketplace/licenses"
        );

        // Provision theme pages into the customer site
        $this->provisionThemePages($order);
    }

    /**
     * SP-02 (TASK 2-SEC): marketplace purchase fulfillment used to call its
     * own private /builder/api/theme/provision webhook
     * (ThemePageProvisionService::provision(), a bare Page::create() with
     * its own hand-rolled slug-uniqueness loop — no
     * ThemeInstanceRevisionService, no CanvasReservedSlugs, no revision
     * history), a second, independent Theme-install authority alongside the
     * one ThemeCanvasController::installToSite()/syncPagesForSite() already
     * used. This now calls the SAME shared ThemeInstallGateway those two
     * actions use, i.e. the same canonical /builder/api/theme/install
     * endpoint / ThemeInstanceRevisionService::record() chain — purchase
     * fulfillment has no private page-provisioning implementation any more
     * (THEME_INSTALL_MUTATION_AUTHORITIES = 1, PURCHASE_DIRECT_PAGE_CREATE = 0).
     * A reserved-slug/page-namespace collision on the customer site is
     * therefore handled by the exact same canonical guard as a manual
     * install, not a second bespoke check here.
     */
    protected function provisionThemePages(ThemePurchaseOrder $order): void
    {
        try {
            $theme = $order->theme;
            $site  = $order->site;

            if (!$theme || !$site) {
                return;
            }

            $result = app(ThemeInstallGateway::class)->install($theme, $site);

            if ($result->success) {
                Log::info('ThemeProvision: theme installed via canonical authority', [
                    'order_id'    => $order->id,
                    'site_id'     => $site->id,
                    'theme_id'    => $theme->id,
                    'revision_id' => $result->body['revision_id'] ?? null,
                ]);
            } else {
                Log::warning('ThemeProvision: canonical install failed', [
                    'order_id'   => $order->id,
                    'site_id'    => $site->id,
                    'theme_id'   => $theme->id,
                    'error_type' => $result->errorType,
                    'message'    => $result->message,
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('ThemeProvision: exception', [
                'order_id' => $order->id,
                'message'  => $e->getMessage(),
            ]);
        }
    }
}
