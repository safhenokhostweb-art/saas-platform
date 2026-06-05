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
use Illuminate\Support\Carbon;
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

            $data['priority']      = $data['priority'] ?? 'normal';
            $data['ticket_number'] = $this->generateTicketNumber();

            // Section K: auto-set FR initial status
            // Only site admins (customer admins) may create feature_request tickets.
            // SaaS platform admins (is_admin && !is_site_admin) cannot create FRs.
            $requestedType = $data['type'] ?? Ticket::TYPE_SUPPORT;
            $user          = auth()->user();
            $isSaasAdmin   = $user && ($user->is_admin ?? false) && !($user->is_site_admin ?? false);
            if ($requestedType === Ticket::TYPE_FEATURE_REQUEST && $isSaasAdmin) {
                $requestedType = Ticket::TYPE_SUPPORT; // silently downgrade
            }
            $data['type'] = $requestedType;

            if ($requestedType === Ticket::TYPE_FEATURE_REQUEST) {
                $data['fr_status']            = $data['fr_status'] ?? Ticket::FR_SUBMITTED;
                $data['fr_status_updated_at'] = now();
            }

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
    public function getStats(?int $siteId = null, bool $platformOnly = false): array
    {
        return $this->ticketRepository->getStats($siteId, $platformOnly);
    }

    /**
     * {@inheritDoc}
     */
    public function delete(Ticket $ticket): bool
    {
        return (bool) $ticket->delete();
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

        // Always use config-file values so the gate check (canUploadAttachments) and
        // this storage step stay in sync. DB overrides are intentionally not used here
        // to prevent silent "uploads enabled in UI, disabled in storage" discrepancies.
        $maxSizeKb   = (int) config('tickets.attachments.max_size',  5120);
        $maxFiles    = (int) config('tickets.attachments.max_files', 5);
        $allowedExts = array_filter((array) config('tickets.attachments.allowed', []));

        // Extension → MIME whitelist (executable types explicitly blocked)
        $extToMime = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'webp' => ['image/webp'],
            'pdf'  => ['application/pdf'],
            'txt'  => ['text/plain'],
            'csv'  => ['text/plain', 'text/csv', 'application/csv'],
            'zip'  => ['application/zip', 'application/x-zip-compressed'],
            'doc'  => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls'  => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];

        $allowedMimes = [];
        foreach ($allowedExts as $ext) {
            if (isset($extToMime[$ext])) {
                array_push($allowedMimes, ...$extToMime[$ext]);
            }
        }
        $allowedMimes = array_unique($allowedMimes) ?: [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'text/plain',
        ];

        $uploadedFiles = [];
        $sitePrefix    = $siteId ? "site-{$siteId}/" : 'unscoped/';

        foreach ($files as $file) {
            if (count($uploadedFiles) >= $maxFiles) {
                break;
            }

            // Skip invalid or missing files
            if (!$file instanceof UploadedFile || !$file->isValid() || $file->getSize() === 0) {
                continue;
            }

            // Verify file actually exists
            if (!$file->getRealPath() || !file_exists($file->getRealPath())) {
                continue;
            }

            // Enforce per-file size limit from settings
            if ($file->getSize() > $maxSizeKb * 1024) {
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

            $subdir = 'ticket-attachments/' . $sitePrefix . now()->format('Y/m');

            // Try configured disk first, fall back to public so uploads never silently fail
            $configuredDisk = config('tickets.attachments.disk', 'private');
            $disk  = $configuredDisk;
            $path  = false;

            try { $path = $file->store($subdir, $configuredDisk); } catch (\Throwable) {}

            if (!$path && $configuredDisk !== 'public') {
                $disk = 'public';
                try { $path = $file->store($subdir, 'public'); } catch (\Throwable) {}
            }

            if (!$path) {
                continue;
            }

            $url = ($disk === 'public')
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($path)
                : null;

            $uploadedFiles[] = [
                'name' => $originalName,
                'path' => $path,
                'url'  => $url,
                'size' => $file->getSize(),
                'mime' => $mime,
                'disk' => $disk,
            ];
        }

        return $uploadedFiles;
    }
}
