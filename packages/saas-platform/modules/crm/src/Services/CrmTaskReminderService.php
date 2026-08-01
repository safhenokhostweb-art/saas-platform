<?php

namespace SaaSPlatform\Modules\Crm\Services;

use SaaSPlatform\CoreUtils\Contracts\Services\SiteNotificationDispatcherInterface;
use SaaSPlatform\Modules\Crm\Models\CrmOpportunity;
use SaaSPlatform\Modules\Crm\Models\CrmTask;

/**
 * Phase 20 — Part 6: three conditions, each notified at most once (see
 * reminder_sent_at/stale_notified_at) — deadline approaching, task
 * overdue, opportunity with no activity in STALE_DAYS. Dispatches via
 * SiteNotificationDispatcherInterface (Part 7's extension point) — never
 * a parallel notification mechanism, and a no-op if nothing has bound a
 * real implementation (see that interface's own docblock).
 */
class CrmTaskReminderService
{
    private const STALE_DAYS = 14;

    public function run(): array
    {
        $counts = ['deadline' => 0, 'overdue' => 0, 'stale_opportunity' => 0];

        if (!app()->bound(SiteNotificationDispatcherInterface::class)) {
            return $counts;
        }

        $dispatcher = app(SiteNotificationDispatcherInterface::class);

        CrmTask::pending()->notYetReminded()
            ->whereNotNull('remind_at')->where('remind_at', '<=', now())
            ->each(function (CrmTask $task) use ($dispatcher, &$counts) {
                $dispatcher->notifySiteAdmin($task->site_id, 'یادآوری وظیفه', "وظیفه «{$task->title}» نزدیک به سررسید است.");
                $task->update(['reminder_sent_at' => now()]);
                $counts['deadline']++;
            });

        CrmTask::overdue()->notYetReminded()
            ->each(function (CrmTask $task) use ($dispatcher, &$counts) {
                $dispatcher->notifySiteAdmin($task->site_id, 'وظیفه عقب‌افتاده', "وظیفه «{$task->title}» از سررسید گذشته است.");
                $task->update(['reminder_sent_at' => now()]);
                $counts['overdue']++;
            });

        CrmOpportunity::open()->active()
            ->whereNull('stale_notified_at')
            ->get()
            ->filter(fn (CrmOpportunity $o) => $this->hasNoRecentActivity($o))
            ->each(function (CrmOpportunity $o) use ($dispatcher, &$counts) {
                $dispatcher->notifySiteAdmin($o->site_id, 'معامله بدون فعالیت', "معامله «{$o->name}» در " . self::STALE_DAYS . ' روز اخیر هیچ فعالیتی نداشته است.');
                $o->update(['stale_notified_at' => now()]);
                $counts['stale_opportunity']++;
            });

        return $counts;
    }

    private function hasNoRecentActivity(CrmOpportunity $opportunity): bool
    {
        $threshold = now()->subDays(self::STALE_DAYS);

        if ($opportunity->created_at->gt($threshold)) {
            return false;
        }

        return !$opportunity->activities()->where('occurred_at', '>=', $threshold)->exists();
    }
}
