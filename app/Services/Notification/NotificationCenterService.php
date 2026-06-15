<?php
namespace App\Services\Notification;

use App\Models\NotificationCenterLog;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Central notification hub.
 *
 * Usage (manual send from admin):
 *   app(NotificationCenterService::class)->notify([
 *       'type'          => 'custom',
 *       'priority'      => 'high',
 *       'title'         => 'عنوان پیام',
 *       'message'       => 'متن',
 *       'audience_type' => 'platform_admin',
 *       'channel'       => 'both',
 *   ]);
 *
 * Usage (system auto):
 *   NotificationCenterService::siteDown($site, 'سایت پاسخ نمی‌دهد');
 */
class NotificationCenterService
{
    public function __construct(private NotificationDispatcher $dispatcher) {}

    // ── Core ──────────────────────────────────────────────────────────────

    public function notify(array $payload): NotificationCenterLog
    {
        $priority = $payload['priority'] ?? 'medium';
        $channel  = $payload['channel']  ?? $this->defaultChannel($priority);

        $log = NotificationCenterLog::create([
            'type'          => $payload['type']          ?? 'system',
            'priority'      => $priority,
            'title'         => $payload['title'],
            'message'       => $payload['message']       ?? null,
            'data'          => $payload['data']          ?? null,
            'link'          => $payload['link']          ?? null,
            'channel'       => $channel,
            'audience_type' => $payload['audience_type'] ?? 'platform_admin',
            'site_id'       => $payload['site_id']       ?? null,
            'created_by'    => $payload['created_by']    ?? (Auth::check() ? Auth::id() : null),
        ]);

        try {
            $this->dispatcher->dispatch($log);
        } catch (\Throwable $e) {
            Log::warning('NotificationCenterService: dispatch error', [
                'log_id' => $log->id,
                'error'  => $e->getMessage(),
            ]);
        }

        return $log;
    }

    // ── Convenience shortcuts ─────────────────────────────────────────────

    public function siteDown(Site $site, string $detail = ''): NotificationCenterLog
    {
        return $this->notify([
            'type'          => 'site_down',
            'priority'      => 'critical',
            'title'         => "خرابی سایت: {$site->title}",
            'message'       => $detail ?: "سایت {$site->domain} پاسخگو نیست",
            'audience_type' => 'platform_admin',
            'channel'       => 'both',
            'site_id'       => $site->id,
            'link'          => '/admin/infrastructure/monitoring',
        ]);
    }

    public function siteRecovery(Site $site): NotificationCenterLog
    {
        return $this->notify([
            'type'          => 'site_recovery',
            'priority'      => 'medium',
            'title'         => "بازیابی سایت: {$site->title}",
            'message'       => "سایت {$site->domain} مجدداً در دسترس است",
            'audience_type' => 'platform_admin',
            'channel'       => 'in_app',
            'site_id'       => $site->id,
            'link'          => '/admin/infrastructure/monitoring',
        ]);
    }

    public function infrastructure(string $title, string $message, string $priority = 'high', ?int $siteId = null): NotificationCenterLog
    {
        return $this->notify([
            'type'          => 'infrastructure',
            'priority'      => $priority,
            'title'         => $title,
            'message'       => $message,
            'audience_type' => 'platform_admin',
            'channel'       => in_array($priority, ['critical', 'high'], true) ? 'both' : 'in_app',
            'site_id'       => $siteId,
            'link'          => '/admin/infrastructure/monitoring',
        ]);
    }

    public function supportTicket(string $title, string $message = '', ?int $siteId = null): NotificationCenterLog
    {
        return $this->notify([
            'type'          => 'support_ticket',
            'priority'      => 'medium',
            'title'         => $title,
            'message'       => $message,
            'audience_type' => 'platform_admin',
            'channel'       => 'in_app',
            'site_id'       => $siteId,
            'link'          => '/admin/support-tickets',
        ]);
    }

    public function purchase(string $title, string $message = '', ?int $siteId = null): NotificationCenterLog
    {
        return $this->notify([
            'type'          => 'purchase',
            'priority'      => 'low',
            'title'         => $title,
            'message'       => $message,
            'audience_type' => 'platform_admin',
            'channel'       => 'in_app',
            'site_id'       => $siteId,
        ]);
    }

    public function renewal(string $title, string $message = '', ?int $siteId = null): NotificationCenterLog
    {
        return $this->notify([
            'type'          => 'renewal',
            'priority'      => 'low',
            'title'         => $title,
            'message'       => $message,
            'audience_type' => 'platform_admin',
            'channel'       => 'in_app',
            'site_id'       => $siteId,
        ]);
    }

    public function deploy(string $title, string $message = ''): NotificationCenterLog
    {
        return $this->notify([
            'type'          => 'deploy',
            'priority'      => 'low',
            'title'         => $title,
            'message'       => $message,
            'audience_type' => 'platform_admin',
            'channel'       => 'in_app',
        ]);
    }

    public function incidentResolved(\App\Models\Infrastructure\InfraIncident $incident, \App\Models\Site $site): NotificationCenterLog
    {
        return $this->notify([
            'type'          => 'incident_resolved',
            'priority'      => 'medium',
            'title'         => "حادثه برطرف شد: {$incident->title}",
            'message'       => "مشکل در سایت {$site->domain} رفع گردید. مشتری از طریق SMS مطلع شد.",
            'audience_type' => 'platform_admin',
            'channel'       => 'in_app',
            'site_id'       => $site->id,
            'link'          => "/admin/incidents/{$incident->id}",
        ]);
    }

    // ── Read / Delete ─────────────────────────────────────────────────────

    public function markRead(int $id): void
    {
        NotificationCenterLog::find($id)?->markRead();
    }

    public function markAllRead(string $audienceType = 'platform_admin'): int
    {
        return NotificationCenterLog::where('audience_type', $audienceType)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function delete(int $id): void
    {
        NotificationCenterLog::destroy($id);
    }

    // ── Stats ─────────────────────────────────────────────────────────────

    public function stats(): array
    {
        $today = now()->startOfDay();

        return [
            'total_today'       => NotificationCenterLog::where('created_at', '>=', $today)->count(),
            'unread'            => NotificationCenterLog::unread()->count(),
            'critical_open'     => NotificationCenterLog::unread()->where('priority', 'critical')->count(),
            'sms_sent_today'    => NotificationCenterLog::where('created_at', '>=', $today)->whereNotNull('sms_sent_at')->count(),
            'total_all_time'    => NotificationCenterLog::count(),
            'by_type'           => NotificationCenterLog::selectRaw('type, COUNT(*) as total')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('type')
                ->pluck('total', 'type'),
            'by_priority'       => NotificationCenterLog::selectRaw('priority, COUNT(*) as total')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('priority')
                ->pluck('total', 'priority'),
            'daily_trend'       => NotificationCenterLog::selectRaw('DATE(created_at) as day, COUNT(*) as total')
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('day')
                ->orderBy('day')
                ->get(),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function defaultChannel(string $priority): string
    {
        return in_array($priority, ['critical', 'high'], true) ? 'both' : 'in_app';
    }
}
