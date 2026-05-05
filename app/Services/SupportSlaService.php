<?php

namespace App\Services;

use App\Models\SupportTicket;
use Carbon\Carbon;

/**
 * منطق SLA برای تیکت‌های پشتیبانی.
 * اهداف پاسخ و حل بر اساس support_level تیکت از config/support.php خوانده می‌شود.
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
     * آیا SLA اولین پاسخ نقض شده؟
     */
    public function isResponseBreached(SupportTicket $ticket): bool
    {
        if ($ticket->first_response_at) {
            return false;
        }
        $hours = $this->getResponseHours($ticket->support_level ?? 'basic');
        $deadline = $ticket->created_at->copy()->addHours($hours);
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
        $deadline = $ticket->created_at->copy()->addHours($hours);
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
     * مهلت اولین پاسخ (Carbon).
     */
    public function getResponseDeadline(SupportTicket $ticket): Carbon
    {
        $hours = $this->getResponseHours($ticket->support_level ?? 'basic');
        return $ticket->created_at->copy()->addHours($hours);
    }

    /**
     * مهلت حل تیکت (Carbon یا null).
     */
    public function getResolutionDeadline(SupportTicket $ticket): ?Carbon
    {
        $hours = $this->getResolutionHours($ticket->support_level ?? 'basic');
        if ($hours === null) {
            return null;
        }
        return $ticket->created_at->copy()->addHours($hours);
    }
}
