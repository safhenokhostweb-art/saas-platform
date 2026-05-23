<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;

class TicketService
{
    /**
     * Get all tickets with optional filters.
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        $query = Ticket::query()->with(['site', 'assignee']);

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

        return $query->orderBy('created_at', 'desc')
            ->paginate(config('tickets.pagination', 20));
    }

    /**
     * Get a ticket by ID.
     */
    public function getById(int $id): ?Ticket
    {
        return Ticket::with(['site', 'assignee', 'replies.user'])->find($id);
    }

    /**
     * Create a new ticket.
     */
    public function create(array $data, ?array $files = null): Ticket
    {
        return DB::transaction(function () use ($data, $files) {
            $uploadedFiles = $this->handleFileUploads($files);
            if (!empty($uploadedFiles)) {
                $data['attachments'] = $uploadedFiles;
            }

            $data['priority'] = $data['priority'] ?? 'normal';
            $data['ticket_number'] = $this->generateTicketNumber();

            $ticket = Ticket::create($data);

            return $ticket;
        });
    }

    /**
     * Update ticket status.
     */
    public function updateStatus(Ticket $ticket, string $status): Ticket
    {
        return DB::transaction(function () use ($ticket, $status) {
            $updates = ['status' => $status];

            if ($status === Ticket::STATUS_CLOSED) {
                $updates['closed_at'] = now();
                $updates['resolved_at'] = now();
            } elseif ($ticket->status === Ticket::STATUS_CLOSED) {
                $updates['closed_at'] = null;
                $updates['resolved_at'] = null;
            }

            $ticket->update($updates);

            return $ticket->fresh();
        });
    }

    /**
     * Assign ticket to a user.
     */
    public function assignTo(Ticket $ticket, ?int $userId): Ticket
    {
        return DB::transaction(function () use ($ticket, $userId) {
            $ticket->update(['assigned_to' => $userId]);

            return $ticket->fresh();
        });
    }

    /**
     * Add a reply to a ticket.
     */
    public function addReply(Ticket $ticket, array $data, ?array $files = null): Ticket
    {
        if ($ticket->status === Ticket::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'message' => 'این تیکت بسته شده است و قابل پاسخ‌دهی نیست.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $data, $files) {
            $uploadedFiles = $this->handleFileUploads($files);
            if (!empty($uploadedFiles)) {
                $data['attachments'] = $uploadedFiles;
            }

            $data['ticket_id'] = $ticket->id;

            TicketReply::create($data);

            if (!$ticket->first_response_at && ($data['author_type'] ?? '') === TicketReply::AUTHOR_STAFF) {
                $ticket->update(['first_response_at' => now()]);
            }

            if ($ticket->status === Ticket::STATUS_OPEN) {
                $ticket->update(['status' => Ticket::STATUS_IN_PROGRESS]);
            }

            return $ticket->fresh(['site', 'assignee', 'replies.user']);
        });
    }

    /**
     * Get ticket statistics.
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

    /**
     * Generate a unique ticket number.
     */
    protected function generateTicketNumber(): string
    {
        $prefix = config('tickets.ticket_number_prefix', 'ST');
        $year = now()->format('Y');
        $count = Ticket::withTrashed()->whereYear('created_at', $year)->count();

        return sprintf('%s-%s-%05d', $prefix, $year, $count + 1);
    }

    /**
     * Handle file uploads for attachments.
     */
    private function handleFileUploads(?array $files): array
    {
        if (empty($files)) {
            return [];
        }

        $uploadedFiles = [];
        foreach ($files as $file) {
            if (!$file || !$file->isValid()) {
                continue;
            }

            $path = $file->store('tickets/' . now()->format('Y/m'), 'public');
            $uploadedFiles[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ];
        }

        return $uploadedFiles;
    }
}
