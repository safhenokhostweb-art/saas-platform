<?php

namespace App\Services;

use App\Exceptions\SupportPortalException;
use App\Models\Site;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Contracts\SiteContextContract;

/**
 * FINAL PRE-MEDIA BASELINE — single-authority convergence.
 *
 * Site identity for every method here now comes exclusively from
 * SiteContextContract, established by AuthenticateTenantApi (credential
 * extraction, license validation, tenant/site resolution — all one
 * authority, see that middleware's own docblock). This class previously
 * re-derived site identity itself from a raw `license_key`/`domain` pair
 * via its own resolveLicensedSite() — a second, competing authority that,
 * after AuthenticateTenantApi's Phase 8.0 hardening started stripping
 * license_key from the request via SecuritySanitizer::scrubRequest()
 * (so it can never appear in dumps/logs), made every caller of this class
 * unreachable: "Undefined array key license_key" the moment any of these
 * methods tried to re-derive the site.
 *
 * Caller audit (mandatory before this change): every public method here is
 * called exclusively from App\Http\Controllers\Api\SupportTicketController,
 * and every one of that controller's actions is routed exclusively through
 * `Route::prefix('support-tickets')->middleware(['throttle:30,1',
 * 'tenant.api'])` (routes/api.php) — i.e. AuthenticateTenantApi always runs
 * first and always establishes SiteContextContract before any of these
 * methods can be reached. There is no other caller, legacy or otherwise
 * (SaaSPlatform\Modules\Tickets\Services\TicketPortalService, used by
 * packages/.../TicketSubmissionController, is an entirely separate,
 * session-authenticated class in a different package — not a caller of
 * this one). resolveLicensedSite()'s own license re-validation is now
 * fully redundant with what the middleware already did; only its
 * ticket-domain-specific "is support access still active" business check
 * (unrelated to license validity itself) is preserved, renamed
 * verifiedSite().
 *
 * Fails closed: no verified site context (SiteContextContract::hasSite()
 * false) always throws before any domain operation runs — no default
 * site, no request-supplied site_id ever trusted, no license_key
 * fallback. Zero additional DB queries introduced (Site::find() by the
 * already-resolved id is the same single lookup resolveLicensedSite() did
 * — LicenseServerService::validate()'s own separate query is removed
 * entirely, a net reduction).
 */
class SupportPortalService
{
    public function __construct(
        private readonly SupportLicenseService $supportLicenseService,
        private readonly SupportTicketService $supportTicketService,
        private readonly SiteContextContract $siteContext,
    ) {}

    public function listTickets(): Collection
    {
        $site = $this->verifiedSite(false);

        return $this->supportTicketService->getBySiteId($site->id);
    }

    public function createTicket(array $data): SupportTicket
    {
        $site = $this->verifiedSite(true);

        return $this->supportTicketService->create([
            'site_id' => $site->id,
            'subject' => $data['subject'],
            'message' => $data['message'],
            'customer_mobile' => $data['customer_mobile'],
            'customer_name'   => $data['customer_name'] ?? null,
            'priority' => $data['priority'] ?? SupportTicket::PRIORITY_NORMAL,
            'category' => $data['category'] ?? null,
            'tags' => $data['tags'] ?? null,
            'attachments' => $data['attachments'] ?? [],
        ]);
    }

    public function addCustomerReply(int $ticketId, array $data): SupportTicket
    {
        $site = $this->verifiedSite(true);

        // Cross-tenant boundary: getByIdForSite() scopes the lookup itself
        // to $site->id (the authenticated site), so a ticket ID belonging
        // to a different site is indistinguishable from a non-existent one
        // here — never trusted from the ticket ID alone.
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

    public function unreadCount(): int
    {
        $site = $this->verifiedSite(false);

        return $this->supportTicketService->countUnreadBySiteId($site->id);
    }

    /**
     * The one remaining site-scoped business check this class owns:
     * whether the already-authenticated site's SUPPORT access (distinct
     * from license validity itself, already proven by
     * AuthenticateTenantApi before this class is ever reached) is
     * currently active. Fails closed — no context, no site row, or support
     * expired all throw before any domain mutation runs.
     */
    private function verifiedSite(bool $requireActiveSupport): Site
    {
        if (!$this->siteContext->hasSite()) {
            throw new SupportPortalException('unauthenticated', 'احراز هویت الزامی است.', 401);
        }

        $site = Site::query()->find($this->siteContext->requireSiteId());
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
