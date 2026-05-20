<?php
namespace App\Repositories;

use App\Contracts\Repositories\SupportTicketRepositoryInterface;
use App\Models\SupportTicket;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SupportTicketRepository implements SupportTicketRepositoryInterface
{
    private const CACHE_TTL = 900;
    private const PER_PAGE = 20;

    private const RELATIONS_DEFAULT = ['site', 'assignee'];

    public function search(array $filters, string $sortBy = 'created_at', string $sortDir = 'desc', int $perPage = 20): LengthAwarePaginator
    {
        $cacheKey = 'support_tickets_search_' . md5(serialize($filters) . $sortBy . $sortDir . request('page', 1) . $perPage);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters, $sortBy, $sortDir, $perPage) {
            $query = SupportTicket::with(self::RELATIONS_DEFAULT)
                ->orderBy($sortBy, $sortDir);

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['priority'])) {
                $query->where('priority', $filters['priority']);
            }

            if (!empty($filters['site_id'])) {
                $query->where('site_id', $filters['site_id']);
            }

            if (!empty($filters['site_ids'])) {
                $query->whereIn('site_id', (array)$filters['site_ids']);
            }

            if (!empty($filters['assigned_to'])) {
                $query->where('assigned_to', $filters['assigned_to']);
            }

            if (!empty($filters['category'])) {
                $query->where('category', $filters['category']);
            }

            if (!empty($filters['q'])) {
                $q = $filters['q'];
                $query->where(function ($query) use ($q) {
                    $query->where('subject', 'like', "%{$q}%")
                          ->orWhere('ticket_number', 'like', "%{$q}%")
                          ->orWhere('customer_email', 'like', "%{$q}%")
                          ->orWhere('customer_name', 'like', "%{$q}%");
                });
            }

            if (!empty($filters['created_from'])) {
                $query->whereDate('created_at', '>=', $filters['created_from']);
            }
            if (!empty($filters['created_to'])) {
                $query->whereDate('created_at', '<=', $filters['created_to']);
            }

            return $query->paginate($perPage);
        });
    }

    public function findById(int $id, array $relations = []): ?SupportTicket
    {
        $relations = $relations ?: self::RELATIONS_DEFAULT;
        return Cache::remember("support_ticket_{$id}_" . md5(implode(',', $relations)), self::CACHE_TTL, function () use ($id, $relations) {
            return SupportTicket::with($relations)->find($id);
        });
    }

    public function findByTicketNumber(string $ticketNumber): ?SupportTicket
    {
        return SupportTicket::where('ticket_number', $ticketNumber)->first();
    }

    public function getBySiteId(int $siteId, array $relations = []): Collection
    {
        $relations = $relations ?: self::RELATIONS_DEFAULT;
        return SupportTicket::with($relations)
            ->where('site_id', $siteId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function create(array $data): SupportTicket
    {
        $ticket = SupportTicket::create($data);
        $this->clearCache();
        return $ticket;
    }

    public function update(SupportTicket $ticket, array $data): SupportTicket
    {
        $ticket->update($data);
        $this->clearCache();
        return $ticket->fresh(self::RELATIONS_DEFAULT);
    }

    public function getStats(): array
    {
        return Cache::remember('support_ticket_stats', self::CACHE_TTL, function () {
            return [
                'total'           => SupportTicket::count(),
                'open'            => SupportTicket::where('status', SupportTicket::STATUS_OPEN)->count(),
                'in_progress'     => SupportTicket::where('status', SupportTicket::STATUS_IN_PROGRESS)->count(),
                'closed'          => SupportTicket::where('status', SupportTicket::STATUS_CLOSED)->count(),
                'high_priority'   => SupportTicket::where('priority', SupportTicket::PRIORITY_HIGH)->count(),
                'new_this_month'  => SupportTicket::whereMonth('created_at', now()->month)
                    ->whereYear('created_at', now()->year)->count(),
            ];
        });
    }

    public function clearCache(): void
    {
        Cache::flush();
    }
}
