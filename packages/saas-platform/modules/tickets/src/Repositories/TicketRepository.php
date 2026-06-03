<?php

namespace SaaSPlatform\Modules\Tickets\Repositories;

use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TicketRepository implements TicketRepositoryInterface
{
    // ─── Admin-context methods ────────────────────────────────────────────────
    // These use withoutTenantIsolation() so platform admins see all sites.
    // Tenant isolation is enforced at the API layer (AuthenticateTenantApi middleware).

    public function search(array $filters, string $sortBy = 'created_at', string $sortDir = 'desc', int $perPage = 20): LengthAwarePaginator
    {
        $query = Ticket::withoutTenantIsolation()
            ->select(['id','ticket_number','subject','status','priority','category',
                      'customer_name','customer_mobile','assigned_to','site_id',
                      'created_at','updated_at','closed_at','first_response_at','resolved_at'])
            ->with(['assignee:id,mobile']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        // site_id=null → تیکت‌های پلتفرمی (بدون site)
        // site_id=X → تیکت‌های یک site خاص
        if (array_key_exists('site_id', $filters)) {
            if ($filters['site_id'] === null) {
                $query->whereNull('site_id');
            } elseif (! empty($filters['site_id'])) {
                $query->where('site_id', $filters['site_id']);
            }
        }
        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }
        // کاربر عادی: فقط تیکت‌های خودش
        if (!empty($filters['customer_mobile'])) {
            $query->where('customer_mobile', $filters['customer_mobile']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(fn($q) =>
                $q->where('subject', 'like', "%{$s}%")
                  ->orWhere('ticket_number', 'like', "%{$s}%")
            );
        }
        if (!empty($filters['q'])) {
            $s = $filters['q'];
            $query->where(fn($q) =>
                $q->where('subject', 'like', "%{$s}%")
                  ->orWhere('ticket_number', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%")
            );
        }

        $allowedSorts = ['created_at', 'updated_at', 'priority', 'status', 'subject'];
        $sortBy  = in_array($sortBy, $allowedSorts) ? $sortBy : 'created_at';
        $sortDir = $sortDir === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    public function findById(int $id, array $relations = []): ?Ticket
    {
        $query = Ticket::withoutTenantIsolation();

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->find($id);
    }

    public function findByTicketNumber(string $ticketNumber): ?Ticket
    {
        return Ticket::withoutTenantIsolation()->where('ticket_number', $ticketNumber)->first();
    }

    public function getStats(?int $siteId = null, bool $platformOnly = false): array
    {
        $base = Ticket::withoutTenantIsolation();

        if ($platformOnly) {
            $base = $base->whereNull('site_id');
        } elseif ($siteId !== null) {
            $base = $base->where('site_id', $siteId);
        }

        return [
            'total'       => (clone $base)->count(),
            'open'        => (clone $base)->where('status', Ticket::STATUS_OPEN)->count(),
            'in_progress' => (clone $base)->where('status', Ticket::STATUS_IN_PROGRESS)->count(),
            'closed'      => (clone $base)->where('status', Ticket::STATUS_CLOSED)->count(),
        ];
    }

    // ─── Tenant-context methods ───────────────────────────────────────────────
    // The SiteTenantScope global scope applies automatically when tenant_site is
    // present on the request. The explicit site_id WHERE clauses below are an
    // additional layer of safety (double-check) rather than the sole guard.

    public function findByIdForSite(int $id, int $siteId, array $relations = []): ?Ticket
    {
        $query = Ticket::where('id', $id)->where('site_id', $siteId);

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->first();
    }

    public function getBySiteId(int $siteId, array $relations = []): Collection
    {
        $query = Ticket::where('site_id', $siteId);

        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    public function countUnreadBySiteId(int $siteId): int
    {
        return Ticket::where('site_id', $siteId)
            ->where('status', Ticket::STATUS_OPEN)
            ->count();
    }

    public function create(array $data): Ticket
    {
        return Ticket::withoutTenantIsolation()->create($data);
    }

    public function update(Ticket $ticket, array $data): Ticket
    {
        $ticket->update($data);
        return $ticket->fresh();
    }
}
