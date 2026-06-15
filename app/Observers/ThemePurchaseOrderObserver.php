<?php

namespace App\Observers;

use App\Models\ThemePurchaseOrder;
use App\Services\AdminNotificationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ThemePurchaseOrderObserver
{
    public function updated(ThemePurchaseOrder $order): void
    {
        if (!$order->isDirty('status') || $order->status !== 'paid') {
            return;
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

    protected function provisionThemePages(ThemePurchaseOrder $order): void
    {
        try {
            $theme = $order->theme;
            $site  = $order->site;

            if (!$theme || !$site) {
                return;
            }

            $pages = $theme->getPageList();
            if (empty($pages)) {
                return;
            }

            // Determine the customer site URL
            $domain = $site->getDomain();
            if (!$domain) {
                Log::info('ThemeProvision: no domain for site', ['site_id' => $site->id]);
                return;
            }

            // Normalize URL
            $baseUrl = str_starts_with($domain, 'http') ? rtrim($domain, '/') : 'https://' . rtrim($domain, '/');
            $endpoint = $baseUrl . '/builder/api/theme/provision';

            // Use the internal provision token (shared secret) for auth
            $secret = config('services.customer_provision_secret', env('CUSTOMER_PROVISION_SECRET', ''));

            $response = Http::withHeaders([
                'X-Provision-Secret' => $secret,
                'Accept'             => 'application/json',
            ])->timeout(15)->post($endpoint, [
                'theme_id'   => $theme->slug ?? (string) $theme->id,
                'theme_name' => $theme->name,
                'pages'      => $pages,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('ThemeProvision: pages provisioned', [
                    'site_id'  => $site->id,
                    'theme_id' => $theme->id,
                    'created'  => $data['created'] ?? 0,
                    'skipped'  => $data['skipped'] ?? 0,
                ]);
            } else {
                Log::warning('ThemeProvision: provision failed', [
                    'site_id'  => $site->id,
                    'theme_id' => $theme->id,
                    'status'   => $response->status(),
                    'body'     => $response->body(),
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
