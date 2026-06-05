<?php

namespace SaaSPlatform\Modules\Tickets\Contracts\Services;

use SaaSPlatform\Modules\Tickets\Models\Ticket;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface TicketServiceInterface
{
    /**
     * Get all tickets with optional filters.
     *
     * @param array $filters Supported keys: status, priority, site_id, category, q
     * @return LengthAwarePaginator
     */
    public function getAll(array $filters = []): LengthAwarePaginator;

    /**
     * Get a ticket by ID with relations.
     */
    public function getById(int $id): ?Ticket;

    /**
     * Get tickets by site ID.
     */
    public function getBySiteId(int $siteId): Collection;

    /**
     * Get a ticket by ID scoped to a site.
     */
    public function getByIdForSite(int $id, int $siteId): ?Ticket;

    /**
     * Count unread tickets for a site.
     */
    public function countUnreadBySiteId(int $siteId): int;

    /**
     * Create a new ticket with optional file attachments.
     */
    public function create(array $data, ?array $files = null): Ticket;

    /**
     * Update ticket status.
     */
    public function updateStatus(Ticket $ticket, string $status): Ticket;

    /**
     * Assign ticket to a user.
     */
    public function assignTo(Ticket $ticket, ?int $userId): Ticket;

    /**
     * Add a reply to a ticket with optional file attachments.
     */
    public function addReply(Ticket $ticket, array $data, ?array $files = null): Ticket;

    /**
     * Get ticket statistics.
     */
    public function getStats(?int $siteId = null, bool $platformOnly = false): array;

    /**
     * Soft-delete a ticket.
     */
    public function delete(Ticket $ticket): bool;
}
