<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketReplyRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;

class TicketService implements TicketServiceInterface
{
    /**
     * Create a new ticket service instance.
     */
    public function __construct(
        protected TicketRepositoryInterface $ticketRepository,
        protected TicketReplyRepositoryInterface $replyRepository,
    ) {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return $this->ticketRepository->search(
            $filters,
            $filters['sort_by'] ?? 'created_at',
            $filters['sort_dir'] ?? 'desc',
            $filters['per_page'] ?? config('tickets.pagination', 20)
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getById(int $id): ?Ticket
    {
        return $this->ticketRepository->findById($id, ['site', 'assignee', 'replies.user']);
    }

    /**
     * {@inheritDoc}
     */
    public function getBySiteId(int $siteId): Collection
    {
        return $this->ticketRepository->getBySiteId($siteId, ['assignee']);
    }

    /**
     * {@inheritDoc}
     */
    public function getByIdForSite(int $id, int $siteId): ?Ticket
    {
        return $this->ticketRepository->findByIdForSite($id, $siteId, ['site', 'assignee', 'replies.user']);
    }

    /**
     * {@inheritDoc}
     */
    public function countUnreadBySiteId(int $siteId): int
    {
        return $this->ticketRepository->countUnreadBySiteId($siteId);
    }

    /**
     * {@inheritDoc}
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

            return $this->ticketRepository->create($data);
        });
    }

    /**
     * {@inheritDoc}
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

            return $this->ticketRepository->update($ticket, $updates);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function assignTo(Ticket $ticket, ?int $userId): Ticket
    {
        return DB::transaction(function () use ($ticket, $userId) {
            return $this->ticketRepository->update($ticket, ['assigned_to' => $userId]);
        });
    }

    /**
     * {@inheritDoc}
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

            $this->replyRepository->create($data);

            if (!$ticket->first_response_at && ($data['author_type'] ?? '') === TicketReply::AUTHOR_STAFF) {
                $this->ticketRepository->update($ticket, ['first_response_at' => now()]);
            }

            if ($ticket->status === Ticket::STATUS_OPEN) {
                $this->ticketRepository->update($ticket, ['status' => Ticket::STATUS_IN_PROGRESS]);
            }

            return $this->ticketRepository->findById($ticket->id, ['site', 'assignee', 'replies.user']);
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getStats(): array
    {
        return $this->ticketRepository->getStats();
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
