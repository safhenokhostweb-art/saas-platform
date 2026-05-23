<?php

namespace SaaSPlatform\CoreUtils\Services;

use Carbon\Carbon;

class SupportSlaService
{
    /**
     * Business hours configuration (default: 9:00 - 18:00, Sat-Thu).
     */
    protected array $businessHours = [
        'start' => '09:00',
        'end'   => '18:00',
    ];

    /**
     * Business days (0=Sunday, 6=Saturday in Iran calendar context).
     * Saturday to Thursday are business days in Iran.
     */
    protected array $businessDays = [0, 1, 2, 3, 4, 5]; // Sat-Thu

    /**
     * Set custom business hours.
     */
    public function setBusinessHours(string $start, string $end): self
    {
        $this->businessHours = compact('start', 'end');
        return $this;
    }

    /**
     * Calculate the SLA deadline based on priority.
     *
     * Priority levels and their SLA hours:
     * - high: 4 hours
     * - normal: 24 hours
     * - low: 48 hours
     */
    public function calculateDeadline(string $priority = 'normal', ?Carbon $startTime = null): Carbon
    {
        $startTime = $startTime ?? now();
        $slaHours = match ($priority) {
            'high'   => 4,
            'normal' => 24,
            'low'    => 48,
            default  => 24,
        };

        return $this->addBusinessHours($startTime, $slaHours);
    }

    /**
     * Check if a ticket is within SLA.
     */
    public function isWithinSla(Carbon $createdAt, Carbon $currentTime, string $priority = 'normal'): bool
    {
        $deadline = $this->calculateDeadline($priority, $createdAt);
        return $currentTime->lessThanOrEqualTo($deadline);
    }

    /**
     * Get remaining SLA time as a human-readable string.
     */
    public function getRemainingTime(Carbon $createdAt, string $priority = 'normal'): string
    {
        $deadline = $this->calculateDeadline($priority, $createdAt);
        $now = now();

        if ($now->greaterThan($deadline)) {
            return 'منقضی شده';
        }

        $remaining = $now->diffInMinutes($deadline);
        $hours = intdiv($remaining, 60);
        $minutes = $remaining % 60;

        if ($hours > 0) {
            return "{$hours} ساعت و {$minutes} دقیقه";
        }

        return "{$minutes} دقیقه";
    }

    /**
     * Add business hours to a given time.
     */
    protected function addBusinessHours(Carbon $start, int $hours): Carbon
    {
        $current = $start->copy();
        $remainingMinutes = $hours * 60;

        while ($remainingMinutes > 0) {
            // Skip to next business day if current day is not a business day
            if (!in_array($current->dayOfWeek, $this->businessDays)) {
                $current->addDay()->setTime(9, 0, 0);
                continue;
            }

            $dayStart = $current->copy()->setTimeFromTimeString($this->businessHours['start']);
            $dayEnd = $current->copy()->setTimeFromTimeString($this->businessHours['end']);

            // If before business hours, jump to start
            if ($current->lessThan($dayStart)) {
                $current = $dayStart;
            }

            // If after business hours, move to next day
            if ($current->greaterThanOrEqualTo($dayEnd)) {
                $current->addDay()->setTime(9, 0, 0);
                continue;
            }

            // Calculate available minutes today
            $availableToday = $current->diffInMinutes($dayEnd, false);
            $useMinutes = min($remainingMinutes, $availableToday);

            $current->addMinutes($useMinutes);
            $remainingMinutes -= $useMinutes;

            if ($remainingMinutes > 0) {
                $current->addDay()->setTime(9, 0, 0);
            }
        }

        return $current;
    }
}
