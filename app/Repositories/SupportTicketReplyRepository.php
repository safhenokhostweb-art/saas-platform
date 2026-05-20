<?php
namespace App\Repositories;

use App\Contracts\Repositories\SupportTicketReplyRepositoryInterface;
use App\Models\SupportTicketReply;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SupportTicketReplyRepository implements SupportTicketReplyRepositoryInterface
{
    private const CACHE_TTL = 900;

    public function getByTicketId(int $ticketId): Collection
    {
        return Cache::remember("support_ticket_replies_{$ticketId}", self::CACHE_TTL, function () use ($ticketId) {
            return SupportTicketReply::where('support_ticket_id', $ticketId)
                ->with('user')
                ->orderBy('created_at')
                ->get();
        });
    }

    public function create(array $data): SupportTicketReply
    {
        $reply = SupportTicketReply::create($data);
        $this->clearCache();
        return $reply;
    }

    public function clearCache(): void
    {
        Cache::flush();
    }
}
