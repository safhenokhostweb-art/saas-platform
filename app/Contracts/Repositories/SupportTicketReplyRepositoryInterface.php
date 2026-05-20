<?php
namespace App\Contracts\Repositories;

use App\Models\SupportTicketReply;
use Illuminate\Support\Collection;

interface SupportTicketReplyRepositoryInterface
{
    public function getByTicketId(int $ticketId): Collection;

    public function create(array $data): SupportTicketReply;
}
