<?php

namespace App\Services;

use App\Exceptions\SupportPortalException;
use App\Models\Site;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SupportPortalService
{
    public function __construct(
        private readonly LicenseServerService $licenseService,
        private readonly SupportLicenseService $supportLicenseService,
        private readonly SupportTicketService $supportTicketService
    ) {}

    public function listTickets(string $licenseKey, string $domain): Collection
    {
        $site = $this->resolveLicensedSite($licenseKey, $domain, false);

        return $this->supportTicketService->getBySiteId($site->id);
    }

    public function createTicket(array $data): SupportTicket
    {
        $site = $this->resolveLicensedSite(
            (string) $data['license_key'],
            (string) $data['domain'],
            true
        );

        return $this->supportTicketService->create([
            'site_id' => $site->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'customer_email' => $data['customer_email'],
            'customer_name' => $data['customer_name'] ?? null,
            'priority' => $data['priority'] ?? SupportTicket::PRIORITY_NORMAL,
            'category' => $data['category'] ?? null,
            'tags' => $data['tags'] ?? null,
            'attachments' => $data['attachments'] ?? [],
        ]);
    }

    public function addCustomerReply(int $ticketId, array $data): SupportTicket
    {
        $site = $this->resolveLicensedSite(
            (string) $data['license_key'],
            (string) $data['domain'],
            true
        );

        $ticket = $this->supportTicketService->getByIdForSite($ticketId, $site->id);
        if (!$ticket) {
            throw new SupportPortalException(
                'ticket_not_found',
                'تیکت مورد نظر یافت نشد.',
                404
            );
        }

        return $this->supportTicketService->addReply($ticket, [
            'message' => $data['message'],
            'author_type' => SupportTicketReply::AUTHOR_CUSTOMER,
            'customer_name' => $data['customer_name'] ?? 'مشتری',
            'attachments' => $data['attachments'] ?? [],
        ]);
    }

    public function unreadCount(string $licenseKey, string $domain): int
    {
        $site = $this->resolveLicensedSite($licenseKey, $domain, false);

        return $this->supportTicketService->countUnreadBySiteId($site->id);
    }

    private function resolveLicensedSite(string $licenseKey, string $domain, bool $requireActiveSupport): Site
    {
        $result = $this->licenseService->validate($licenseKey, $domain);

        if (!($result['valid'] ?? false)) {
            Log::warning('support.portal.license_rejected', [
                'error' => $result['error'] ?? 'invalid_license',
            ]);

            throw new SupportPortalException(
                $result['error'] ?? 'invalid_license',
                $result['message'] ?? 'لایسنس نامعتبر است.',
                403
            );
        }

        $siteId = (int) ($result['site_id'] ?? 0);
        if ($siteId < 1) {
            throw new SupportPortalException('site_not_found', 'سایت مورد نظر یافت نشد.', 403);
        }

        $site = Site::query()->find($siteId);
        if (!$site) {
            throw new SupportPortalException('site_not_found', 'سایت مورد نظر یافت نشد.', 403);
        }

        if ($requireActiveSupport && $site->support_expires_at !== null && !$this->supportLicenseService->canAccessSupport($site)) {
            Log::warning('support.portal.support_access_denied', [
                'site_id' => $site->id,
            ]);

            throw new SupportPortalException(
                'support_expired',
                'دسترسی به پشتیبانی منقضی شده یا هنوز شروع نشده است.',
                403
            );
        }

        return $site;
    }
}
