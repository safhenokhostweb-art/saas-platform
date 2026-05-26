<?php

namespace SaaSPlatform\Modules\Tickets\Services;

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
use SaaSPlatform\Modules\Tickets\Events\TicketCreated;
use SaaSPlatform\Modules\Tickets\Events\TicketReplied;
use SaaSPlatform\Modules\Tickets\Events\TicketStatusChanged;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
        $ticket = $this->ticketRepository->findByIdForSite($id, $siteId, ['site', 'assignee', 'replies.user']);

        if ($ticket) {
            $this->assertTenantIsolation($ticket, $siteId);
        }

        return $ticket;
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
            $uploadedFiles = $this->handleFileUploads($files, $data['site_id'] ?? null);
            if (!empty($uploadedFiles)) {
                $data['attachments'] = $uploadedFiles;
            }

            $data['priority'] = $data['priority'] ?? 'normal';
            $data['ticket_number'] = $this->generateTicketNumber();

            $ticket = $this->ticketRepository->create($data);

            event(new TicketCreated($ticket));

            return $ticket;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function updateStatus(Ticket $ticket, string $status): Ticket
    {
        return DB::transaction(function () use ($ticket, $status) {
            $previousStatus = $ticket->status;
            $updates = ['status' => $status];

            if ($status === Ticket::STATUS_CLOSED) {
                $updates['closed_at'] = now();
                $updates['resolved_at'] = now();
            } elseif ($ticket->status === Ticket::STATUS_CLOSED) {
                $updates['closed_at'] = null;
                $updates['resolved_at'] = null;
            }

            $updated = $this->ticketRepository->update($ticket, $updates);

            event(new TicketStatusChanged($updated, $previousStatus, $status));

            return $updated;
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
            $uploadedFiles = $this->handleFileUploads($files, $ticket->site_id);
            if (!empty($uploadedFiles)) {
                $data['attachments'] = $uploadedFiles;
            }

            $data['ticket_id'] = $ticket->id;

            $reply = $this->replyRepository->create($data);

            if (!$ticket->first_response_at && ($data['author_type'] ?? '') === TicketReply::AUTHOR_STAFF) {
                $this->ticketRepository->update($ticket, ['first_response_at' => now()]);
            }

            if ($ticket->status === Ticket::STATUS_OPEN) {
                $this->ticketRepository->update($ticket, ['status' => Ticket::STATUS_IN_PROGRESS]);
            }

            $refreshed = $this->ticketRepository->findById($ticket->id, ['site', 'assignee', 'replies.user']);

            event(new TicketReplied($refreshed, $reply));

            return $refreshed;
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
     * PHASE 7.2 — TASK 6: Enforce tenant isolation at the service boundary.
     * Throws if the ticket's site_id does not match the expected site context.
     * Prevents cross-tenant data leakage regardless of repository-level filtering.
     */
    public function assertTenantIsolation(Ticket $ticket, int $expectedSiteId): void
    {
        if ($ticket->site_id !== null && $ticket->site_id !== $expectedSiteId) {
            throw new AccessDeniedHttpException(
                "Tenant isolation violation: ticket #{$ticket->id} belongs to site {$ticket->site_id}, not {$expectedSiteId}."
            );
        }
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
     * PHASE 8.0 — Attachments are stored in the PRIVATE disk under a per-site
     * path so they are never directly web-accessible. Access is only via
     * SecureAttachmentController which validates ownership before streaming.
     */
    private function handleFileUploads(?array $files, ?int $siteId = null): array
    {
        if (empty($files)) {
            return [];
        }

        // Allowed MIME types — executable types explicitly blocked
        $allowedMimes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'text/plain', 'text/csv',
            'application/zip', 'application/x-zip-compressed',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        $uploadedFiles = [];
        $sitePrefix    = $siteId ? "site-{$siteId}/" : 'unscoped/';

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }

            $mime = $file->getMimeType();
            if (!in_array($mime, $allowedMimes, true)) {
                continue; // silently skip disallowed types
            }

            // Block dangerous double extensions: e.g. invoice.pdf.php
            $originalName = $file->getClientOriginalName();
            if (preg_match('/\.(php|phtml|phar|exe|sh|bat|cmd|ps1|asp|aspx|jsp|cgi)$/i', $originalName)) {
                continue;
            }

            $name = $file->hashName();
            if ($name === '') {
                continue;
            }

            // Store in private disk — NOT publicly accessible
            $storagePath = 'ticket-attachments/' . $sitePrefix . now()->format('Y/m') . '/' . $name;
            $file->storeAs(
                'ticket-attachments/' . $sitePrefix . now()->format('Y/m'),
                $name,
                'private'
            );

            $uploadedFiles[] = [
                'name' => $originalName,
                'path' => $storagePath,
                'size' => $file->getSize(),
                'mime' => $mime,
                'disk' => 'private',
            ];
        }

        return $uploadedFiles;
    }
}
