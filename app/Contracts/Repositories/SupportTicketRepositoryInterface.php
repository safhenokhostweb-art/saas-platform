<?php
namespace App\Contracts\Repositories;

use App\Models\SupportTicket;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SupportTicketRepositoryInterface
{
    public function search(array $filters, string $sortBy = 'created_at', string $sortDir = 'desc', int $perPage = 20): LengthAwarePaginator;

    public function findById(int $id, array $relations = []): ?SupportTicket;

    public function findByTicketNumber(string $ticketNumber): ?SupportTicket;

    public function findByIdForSite(int $id, int $siteId, array $relations = []): ?SupportTicket;

    public function getBySiteId(int $siteId, array $relations = []): Collection;

    public function countUnreadBySiteId(int $siteId): int;

    public function create(array $data): SupportTicket;

    public function update(SupportTicket $ticket, array $data): SupportTicket;

    public function getStats(): array;
}
