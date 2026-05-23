<?php
namespace App\Contracts\Services;

use App\Models\SupportTicket;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SupportTicketServiceInterface
{
    public function getAll(array $filters = []): LengthAwarePaginator;

    public function getById(int $id): ?SupportTicket;

    public function getBySiteId(int $siteId): Collection;

    public function getByIdForSite(int $id, int $siteId): ?SupportTicket;

    public function countUnreadBySiteId(int $siteId): int;

    public function create(array $data, ?array $files = null): SupportTicket;

    public function updateStatus(SupportTicket $ticket, string $status): SupportTicket;

    public function assignTo(SupportTicket $ticket, ?int $userId): SupportTicket;

    public function addReply(SupportTicket $ticket, array $data, ?array $files = null): SupportTicket;

    public function getStats(): array;
}
