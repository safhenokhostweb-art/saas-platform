<?php

namespace SaaSPlatform\Modules\Tickets\Contracts\Repositories;

use SaaSPlatform\Modules\Tickets\Models\Ticket;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TicketRepositoryInterface
{
    /**
     * Search tickets with filters, sorting, and pagination.
     *
     * @param array  $filters  Supported keys: status, priority, site_id, category, q
     * @param string $sortBy   Column to sort by
     * @param string $sortDir  Sort direction (asc|desc)
     * @param int    $perPage  Items per page
     * @return LengthAwarePaginator
     */
    public function search(array $filters, string $sortBy = 'created_at', string $sortDir = 'desc', int $perPage = 20): LengthAwarePaginator;

    /**
     * Find a ticket by ID with optional relations.
     */
    public function findById(int $id, array $relations = []): ?Ticket;

    /**
     * Find a ticket by ticket number.
     */
    public function findByTicketNumber(string $ticketNumber): ?Ticket;

    /**
     * Find a ticket by ID scoped to a site.
     */
    public function findByIdForSite(int $id, int $siteId, array $relations = []): ?Ticket;

    /**
     * Get tickets by site ID.
     */
    public function getBySiteId(int $siteId, array $relations = []): Collection;

    /**
     * Count unread tickets for a site.
     */
    public function countUnreadBySiteId(int $siteId): int;

    /**
     * Create a new ticket.
     */
    public function create(array $data): Ticket;

    /**
     * Update an existing ticket.
     */
    public function update(Ticket $ticket, array $data): Ticket;

    /**
     * Get ticket statistics.
     */
    public function getStats(?int $siteId = null, bool $platformOnly = false): array;
}
