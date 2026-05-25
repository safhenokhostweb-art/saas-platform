<?php

/**
 * @deprecated PHASE 7.1 — Use SaaSPlatform\Modules\Tickets\Services\TicketService (via TicketServiceInterface) instead.
 *             This service operates on the legacy support_tickets table.
 */

namespace App\Services;

use App\Contracts\Repositories\SupportTicketRepositoryInterface;
use App\Contracts\Repositories\SupportTicketReplyRepositoryInterface;
use App\Contracts\Services\SupportTicketServiceInterface;
use App\Models\Site;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Services\ActivityLogger;
use App\Services\SupportLicenseService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportTicketService implements SupportTicketServiceInterface
{
    public function __construct(
        protected SupportTicketRepositoryInterface $ticketRepository,
        protected SupportTicketReplyRepositoryInterface $replyRepository,
        protected SupportLicenseService $supportLicenseService
    ) {}

    public function getAll(array $filters = []): LengthAwarePaginator
    {
        return $this->ticketRepository->search($filters);
    }

    public function getById(int $id): ?SupportTicket
    {
        return $this->ticketRepository->findById($id, ['site', 'assignee', 'replies.user']);
    }

    public function getBySiteId(int $siteId): Collection
    {
        return $this->ticketRepository->getBySiteId($siteId, ['replies.user']);
    }

    public function getByIdForSite(int $id, int $siteId): ?SupportTicket
    {
        return $this->ticketRepository->findByIdForSite($id, $siteId, ['site', 'assignee', 'replies.user']);
    }

    public function countUnreadBySiteId(int $siteId): int
    {
        return $this->ticketRepository->countUnreadBySiteId($siteId);
    }

    public function create(array $data, ?array $files = null): SupportTicket
    {
        return DB::transaction(function () use ($data, $files) {
            // Handle file uploads
            $uploadedFiles = $this->handleFileUploads($files);
            if (!empty($uploadedFiles)) {
                $data['attachments'] = $uploadedFiles;
            }

            $data['priority'] = $data['priority'] ?? 'normal';

            // Set support level from the tenant site without exposing tenant data outside the use case.
            if (!empty($data['site_id'])) {
                $site = Site::query()->find((int) $data['site_id']);
                if ($site) {
                    $data['support_level'] = $this->supportLicenseService->getLevel($site);
                }
            }

            $ticket = $this->ticketRepository->create($data);

            ActivityLogger::log('support_ticket.created', "تیکت جدید: {$ticket->ticket_number}", $ticket);

            return $ticket;
        });
    }

    public function updateStatus(SupportTicket $ticket, string $status): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $status) {
            $updates = ['status' => $status];

            if ($status === SupportTicket::STATUS_CLOSED) {
                $updates['closed_at'] = now();
                $updates['resolved_at'] = now();
            } elseif ($ticket->status === SupportTicket::STATUS_CLOSED) {
                // بازگشایی مجدد تیکت
                $updates['closed_at'] = null;
                $updates['resolved_at'] = null;
            }

            $ticket = $this->ticketRepository->update($ticket, $updates);

            ActivityLogger::log('support_ticket.status_changed', "تغییر وضعیت تیکت {$ticket->ticket_number} به {$status}", $ticket);

            return $ticket;
        });
    }

    public function assignTo(SupportTicket $ticket, ?int $userId): SupportTicket
    {
        return DB::transaction(function () use ($ticket, $userId) {
            $ticket = $this->ticketRepository->update($ticket, ['assigned_to' => $userId]);

            ActivityLogger::log('support_ticket.assigned', "ارجاع تیکت {$ticket->ticket_number} به کاربر #{$userId}", $ticket);

            return $ticket;
        });
    }

    public function addReply(SupportTicket $ticket, array $data, ?array $files = null): SupportTicket
    {
        if ($ticket->status === SupportTicket::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'message' => 'این تیکت بسته شده است و قابل پاسخ‌دهی نیست.',
            ]);
        }

        return DB::transaction(function () use ($ticket, $data, $files) {
            // Handle file uploads for reply
            $uploadedFiles = $this->handleFileUploads($files);
            if (!empty($uploadedFiles)) {
                $data['attachments'] = $uploadedFiles;
            }

            $data['support_ticket_id'] = $ticket->id;

            $this->replyRepository->create($data);

            // Update first response time if not set
            if (!$ticket->first_response_at && $data['author_type'] === SupportTicketReply::AUTHOR_STAFF) {
                $this->ticketRepository->update($ticket, ['first_response_at' => now()]);
            }

            // Auto change status to in_progress if open
            if ($ticket->status === SupportTicket::STATUS_OPEN) {
                $this->ticketRepository->update($ticket, ['status' => SupportTicket::STATUS_IN_PROGRESS]);
            }

            ActivityLogger::log('support_ticket.reply_added', "پاسخ جدید در تیکت {$ticket->ticket_number}", $ticket);

            return $ticket->fresh(['site', 'assignee', 'replies.user']);
        });
    }

    public function getStats(): array
    {
        return $this->ticketRepository->getStats();
    }

    /**
     * آپلود فایل‌های ضمیمه
     */
    private function handleFileUploads(?array $files): array
    {
        if (empty($files)) {
            return [];
        }

        $uploadedFiles = [];
        foreach ($files as $file) {
            if (!$file || !$file->isValid()) continue;

            $path = $file->store('support-tickets/' . now()->format('Y/m'), 'public');
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
