<?php

namespace App\Services;

use App\Models\SupportTicket;
use Carbon\Carbon;

/**
 * منطق SLA برای تیکت‌های پشتیبانی.
 * اهداف پاسخ و حل بر اساس support_level تیکت از config/support.php خوانده می‌شود.
 * از Business Hours برای محاسبه دقیق‌تر SLA پشتیبانی می‌کند.
 */
class SupportSlaService
{
    public function __construct(
        protected SupportLicenseService $supportLicenseService
    ) {}

    /**
     * حداکثر ساعت تا اولین پاسخ (بر اساس سطح پشتیبانی).
     */
    public function getResponseHours(string $supportLevel): int
    {
        $level = $this->supportLicenseService->getLevelFromConfig($supportLevel);
        $sla = config("support.sla.{$level}", config('support.sla.basic'));
        return (int) ($sla['response_hours'] ?? 24);
    }

    /**
     * حداکثر ساعت تا حل تیکت (null = بدون محدودیت).
     */
    public function getResolutionHours(string $supportLevel): ?int
    {
        $level = $this->supportLicenseService->getLevelFromConfig($supportLevel);
        $sla = config("support.sla.{$level}", config('support.sla.basic'));
        $hours = $sla['resolution_hours'] ?? null;
        return $hours !== null ? (int) $hours : null;
    }

    /**
     * آیا Business Hours فعال است؟
     */
    public function isBusinessHoursEnabled(): bool
    {
        return (bool) config('support.business_hours.enabled', true);
    }

    /**
     * محاسبه مهلت با در نظر گرفتن Business Hours.
     *
     * اگر business_hours فعال باشد، ساعت‌های خارج از ساعات کاری و روزهای تعطیل
     * در محاسبه مهلت لحاظ نمی‌شوند. در غیر این صورت، مهلت ساده (۲۴×۷) محاسبه می‌شود.
     */
    public function addBusinessHours(Carbon $start, int $totalHours): Carbon
    {
        if (!$this->isBusinessHoursEnabled() || $totalHours <= 0) {
            return $start->copy()->addHours($totalHours);
        }

        $bhConfig = config('support.business_hours');
        $tz = $bhConfig['timezone'] ?? 'Asia/Tehran';
        $weekdays = $bhConfig['weekdays'] ?? [1, 2, 3, 4, 5];
        $bhStart = $bhConfig['start'] ?? '09:00';
        $bhEnd = $bhConfig['end'] ?? '17:00';

        $current = $start->copy()->timezone($tz);
        $remaining = $totalHours;
        $maxIterations = 500; // جلوگیری از حلقه بی‌نهایت

        while ($remaining > 0 && $maxIterations-- > 0) {
            // اگر روز تعطیل است، برو به روز بعد
            if (!in_array((int) $current->format('N'), $weekdays)) {
                $current->addDay()->setTime(0, 0, 0);
                continue;
            }

            // امروز یک روز کاری است
            $dayStart = $current->copy()->setTimeFromTimeString($bhStart);
            $dayEnd = $current->copy()->setTimeFromTimeString($bhEnd);

            // اگر قبل از شروع ساعات کاری هستیم، برو به شروع ساعات کاری
            if ($current->lt($dayStart)) {
                $current = $dayStart;
            }

            // اگر بعد از پایان ساعات کاری هستیم، برو به روز بعد
            if ($current->gte($dayEnd)) {
                $current->addDay()->setTime(0, 0, 0);
                continue;
            }

            // محاسبه ساعت باقی‌مانده از امروز
            $hoursLeftToday = $current->diffInHours($dayEnd, false);
            if ($hoursLeftToday <= 0) {
                $current->addDay()->setTime(0, 0, 0);
                continue;
            }

            if ($remaining <= $hoursLeftToday) {
                $current->addHours($remaining);
                $remaining = 0;
            } else {
                $remaining -= $hoursLeftToday;
                $current = $dayEnd;
            }
        }

        return $current;
    }

    /**
     * آیا SLA اولین پاسخ نقض شده؟
     */
    public function isResponseBreached(SupportTicket $ticket): bool
    {
        if ($ticket->first_response_at) {
            return false;
        }
        $hours = $this->getResponseHours($ticket->support_level ?? 'basic');
        $deadline = $this->addBusinessHours($ticket->created_at, $hours);
        return now()->isAfter($deadline);
    }

    /**
     * آیا SLA حل تیکت نقض شده؟
     */
    public function isResolutionBreached(SupportTicket $ticket): bool
    {
        if ($ticket->status === SupportTicket::STATUS_CLOSED) {
            return false;
        }
        $hours = $this->getResolutionHours($ticket->support_level ?? 'basic');
        if ($hours === null) {
            return false;
        }
        $deadline = $this->addBusinessHours($ticket->created_at, $hours);
        return now()->isAfter($deadline);
    }

    /**
     * آیا هر SLA نقض شده؟
     */
    public function hasAnyBreach(SupportTicket $ticket): bool
    {
        return $this->isResponseBreached($ticket) || $this->isResolutionBreached($ticket);
    }

    /**
     * مهلت اولین پاسخ با در نظر گرفتن Business Hours (Carbon).
     */
    public function getResponseDeadline(SupportTicket $ticket): Carbon
    {
        $hours = $this->getResponseHours($ticket->support_level ?? 'basic');
        return $this->addBusinessHours($ticket->created_at, $hours);
    }

    /**
     * مهلت حل تیکت با در نظر گرفتن Business Hours (Carbon یا null).
     */
    public function getResolutionDeadline(SupportTicket $ticket): ?Carbon
    {
        $hours = $this->getResolutionHours($ticket->support_level ?? 'basic');
        if ($hours === null) {
            return null;
        }
        return $this->addBusinessHours($ticket->created_at, $hours);
    }
}
