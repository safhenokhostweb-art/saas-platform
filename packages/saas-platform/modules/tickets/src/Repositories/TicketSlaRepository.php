<?php

namespace SaaSPlatform\Modules\Tickets\Repositories;

use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketSlaRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use Illuminate\Support\Collection;

/**
 * PHASE 7.1 — TASK 4: Ticket SLA Repository
 *
 * Computes SLA state from ticket timestamps.
 * SLA targets are configured in config('tickets.sla').
 * Feature-flagged via config('feature-flags.tickets.sla').
 */
class TicketSlaRepository implements TicketSlaRepositoryInterface
{
    protected function firstResponseTarget(): int
    {
        return config('tickets.sla.first_response_minutes', 60);
    }

    protected function resolutionTarget(): int
    {
        return config('tickets.sla.resolution_minutes', 1440);
    }

    public function getBreachedTickets(?int $siteId = null): Collection
    {
        $query = Ticket::whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_IN_PROGRESS]);

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        return $query->get()->filter(fn (Ticket $t) => $this->isBreached($t));
    }

    public function getTicketsApproachingBreach(?int $siteId = null, int $withinMinutes = 30): Collection
    {
        $query = Ticket::whereIn('status', [Ticket::STATUS_OPEN, Ticket::STATUS_IN_PROGRESS]);

        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        return $query->get()->filter(function (Ticket $t) use ($withinMinutes) {
            $deadline = $this->getResolutionDeadline($t);
            if (!$deadline) {
                return false;
            }
            $minutesLeft = now()->diffInMinutes($deadline, false);
            return $minutesLeft >= 0 && $minutesLeft <= $withinMinutes;
        });
    }

    public function getSlaMetrics(?int $siteId = null): array
    {
        $query = Ticket::query();
        if ($siteId) {
            $query->where('site_id', $siteId);
        }

        $all      = $query->get();
        $breached = $all->filter(fn (Ticket $t) => $this->isBreached($t));

        $responded = $all->filter(fn (Ticket $t) => $t->first_response_at !== null);
        $avgFirstResponse = $responded->isNotEmpty()
            ? $responded->avg(fn (Ticket $t) => $t->created_at->diffInMinutes($t->first_response_at))
            : null;

        return [
            'total'               => $all->count(),
            'breached'            => $breached->count(),
            'breach_rate'         => $all->count() > 0 ? round($breached->count() / $all->count() * 100, 1) : 0,
            'avg_first_response'  => $avgFirstResponse ? round((float) $avgFirstResponse, 1) : null,
            'first_response_target' => $this->firstResponseTarget(),
            'resolution_target'   => $this->resolutionTarget(),
        ];
    }

    public function isBreached(Ticket $ticket): bool
    {
        if ($ticket->status === Ticket::STATUS_CLOSED) {
            return false;
        }

        $deadline = $this->getResolutionDeadline($ticket);
        return $deadline && now()->isAfter($deadline);
    }

    public function getFirstResponseDeadline(Ticket $ticket): ?\DateTimeInterface
    {
        return $ticket->created_at?->addMinutes($this->firstResponseTarget());
    }

    public function getResolutionDeadline(Ticket $ticket): ?\DateTimeInterface
    {
        return $ticket->created_at?->addMinutes($this->resolutionTarget());
    }
}
