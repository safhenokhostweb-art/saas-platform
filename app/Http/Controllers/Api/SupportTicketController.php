<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SupportTicket;
use App\Services\LicenseServerService;
use App\Services\SupportLicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API ثبت تیکت پشتیبانی از customer-site.
 * با اعتبارسنجی لایسنس و دسترسی پشتیبانی، تیکت برای سایت مربوط ثبت می‌شود.
 */
class SupportTicketController extends Controller
{
    public function __construct(
        protected LicenseServerService $licenseService,
        protected SupportLicenseService $supportLicenseService
    ) {}

    /**
     * POST /api/support-tickets
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string',
            'domain' => 'required|string',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'customer_email' => 'required|email',
            'customer_name' => 'nullable|string|max:255',
        ]);

        $result = $this->licenseService->validate(
            $request->license_key,
            $request->domain
        );

        if (!($result['valid'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'invalid_license',
                'message' => $result['message'] ?? 'لایسنس نامعتبر است.',
            ], 403);
        }

        $siteId = $result['site_id'] ?? null;
        if (!$siteId) {
            return response()->json(['success' => false, 'error' => 'site_not_found'], 403);
        }

        $site = Site::find($siteId);
        if (!$site || !$this->supportLicenseService->canAccessSupport($site)) {
            return response()->json([
                'success' => false,
                'error' => 'support_expired',
                'message' => 'دسترسی به پشتیبانی منقضی شده یا هنوز شروع نشده است.',
            ], 403);
        }

        $supportLevel = $this->supportLicenseService->getLevel($site);

        $ticket = SupportTicket::create([
            'site_id' => $siteId,
            'subject' => $request->subject,
            'message' => $request->message,
            'customer_email' => $request->customer_email,
            'customer_name' => $request->customer_name,
            'priority' => 'normal',
            'support_level' => $supportLevel,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تیکت ثبت شد.',
            'ticket_number' => $ticket->ticket_number,
        ]);
    }
}
