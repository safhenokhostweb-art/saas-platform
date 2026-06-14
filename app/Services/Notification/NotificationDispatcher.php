<?php
namespace App\Services\Notification;

use App\Models\NotificationCenterLog;
use App\Models\User;
use App\Models\Customer;
use App\Services\Sms\SmsGatewayResolver;
use Illuminate\Support\Facades\Log;

/**
 * Routes a NotificationCenterLog to its delivery channels.
 * In-app: already stored in DB — this handles SMS only.
 */
class NotificationDispatcher
{
    public function dispatch(NotificationCenterLog $log): void
    {
        if (in_array($log->channel, ['sms', 'both'], true)) {
            $this->sendSms($log);
        }
    }

    // ── SMS ────────────────────────────────────────────────────────────────

    private function sendSms(NotificationCenterLog $log): void
    {
        $gateway = SmsGatewayResolver::resolve();
        if (! $gateway) {
            return;
        }

        $mobiles = $this->resolveMobiles($log);
        if (empty($mobiles)) {
            return;
        }

        $priorityFa = $log->priorityFa();
        $text       = "[{$priorityFa}] {$log->title}";
        if ($log->message) {
            $text .= ': ' . mb_substr($log->message, 0, 100);
        }

        $sent = false;
        foreach ($mobiles as $mobile) {
            $mobile = $this->normalizeMobile((string) $mobile);
            if (! $mobile) {
                continue;
            }
            try {
                $gateway->send($mobile, $text);
                $sent = true;
            } catch (\Throwable $e) {
                Log::warning('NotificationDispatcher: SMS failed', [
                    'mobile' => $mobile,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        if ($sent) {
            $log->markSmsSent();
        }
    }

    // ── Recipient resolution ───────────────────────────────────────────────

    private function resolveMobiles(NotificationCenterLog $log): array
    {
        return match ($log->audience_type) {
            'platform_admin'  => $this->platformAdminMobiles(),
            'site_admin'      => $this->siteAdminMobile($log->site_id),
            'all_site_admins' => $this->allSiteAdminMobiles(),
            'broadcast'       => array_unique(array_merge(
                $this->platformAdminMobiles(),
                $this->allSiteAdminMobiles()
            )),
            default => [],
        };
    }

    private function platformAdminMobiles(): array
    {
        return User::where('is_active', true)
            ->where('is_admin', true)
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->pluck('mobile')
            ->unique()
            ->filter()
            ->values()
            ->all();
    }

    private function siteAdminMobile(?int $siteId): array
    {
        if (! $siteId) {
            return [];
        }

        $site = \App\Models\Site::with('customer')->find($siteId);
        if (! $site || ! $site->customer) {
            return [];
        }

        $mobile = $site->customer->mobile ?? null;
        if (! $mobile && $site->customer->user_id) {
            $mobile = User::find($site->customer->user_id)?->mobile;
        }

        return $mobile ? [$mobile] : [];
    }

    private function allSiteAdminMobiles(): array
    {
        return Customer::whereHas('sites', fn ($q) => $q->where('is_active', true))
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->pluck('mobile')
            ->unique()
            ->filter()
            ->values()
            ->all();
    }

    private function normalizeMobile(string $m): string
    {
        $m = preg_replace('/\D/', '', $m);
        if (str_starts_with($m, '98')) return $m;
        if (str_starts_with($m, '0'))  return '98' . substr($m, 1);
        if (strlen($m) === 10)         return '98' . $m;
        return $m;
    }
}
