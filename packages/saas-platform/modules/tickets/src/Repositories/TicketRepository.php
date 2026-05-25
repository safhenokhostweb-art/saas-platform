<?php

namespace SaaSPlatform\Modules\Tickets\Repositories;

use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TicketRepository implements TicketRepositoryInterface
{
    /**
     * {@inheritDoc}
     */
    public function search(array $filters, string $sortBy = 'created_at', string $sortDir = 'desc', int $perPage = 20): LengthAwarePaginator
    {
        $query = Ticket::query();

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (!empty($filters['site_id'])) {
            $query->where('site_id', $filters['site_id']);
        }

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (!empty($filters['q'])) {
            $search = $filters['q'];
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('ticket_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $allowedSorts = ['created_at', 'updated_at', 'priority', 'status', 'subject'];
        $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'created_at';
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDir)
            ->paginate($perPage);
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id, array $relations = []): ?Ticket
    {
        $query = Ticket::query();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findByTicketNumber(string $ticketNumber): ?Ticket
    {
        return Ticket::where('ticket_number', $ticketNumber)->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdForSite(int $id, int $siteId, array $relations = []): ?Ticket
    {
        $query = Ticket::where('id', $id)->where('site_id', $siteId);

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->first();
    }

    /**
     * {@inheritDoc}
     */
    public function getBySiteId(int $siteId, array $relations = []): Collection
    {
        $query = Ticket::where('site_id', $siteId);

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * {@inheritDoc}
     */
    public function countUnreadBySiteId(int $siteId): int
    {
        return Ticket::where('site_id', $siteId)
            ->where('status', Ticket::STATUS_OPEN)
            ->count();
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): Ticket
    {
        return Ticket::create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);
        return $ticket->fresh();
    }

    /**
     * {@inheritDoc}
     */
    public function getStats(): array
    {
        return [
            'total' => Ticket::count(),
            'open' => Ticket::where('status', Ticket::STATUS_OPEN)->count(),
            'in_progress' => Ticket::where('status', Ticket::STATUS_IN_PROGRESS)->count(),
            'closed' => Ticket::where('status', Ticket::STATUS_CLOSED)->count(),
        ];
    }
}
