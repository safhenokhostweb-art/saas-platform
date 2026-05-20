<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SupportTicket;
use App\Services\LicenseServerService;
use App\Services\SupportLicenseService;
use App\Services\SupportTicketService;
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
        protected SupportLicenseService $supportLicenseService,
        protected SupportTicketService $supportTicketService
    ) {}

    /**
     * GET /api/support-tickets?license_key=...&domain=...
     * دریافت تاریخچه تیکت‌های یک سایت (برای customer-site).
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string',
            'domain' => 'required|string',
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

        $tickets = SupportTicket::with(['replies' => function ($q) {
                $q->orderBy('created_at');
            }])
            ->where('site_id', $siteId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                    'message' => $ticket->message,
                    'status' => $ticket->status,
                    'priority' => $ticket->priority,
                    'support_level' => $ticket->support_level,
                    'assigned_to' => $ticket->assigned_to,
                    'customer_email' => $ticket->customer_email,
                    'category' => $ticket->category,
                    'tags' => $ticket->tags,
                    'attachments' => $ticket->attachments,
                    'closed_at' => $ticket->closed_at?->toISOString(),
                    'first_response_at' => $ticket->first_response_at?->toISOString(),
                    'resolved_at' => $ticket->resolved_at?->toISOString(),
                    'created_at' => $ticket->created_at->toISOString(),
                    'replies' => $ticket->replies->map(function ($reply) {
                        return [
                            'id' => $reply->id,
                            'message' => $reply->message,
                            'author_type' => $reply->author_type,
                            'customer_name' => $reply->customer_name,
                            'attachments' => $reply->attachments,
                            'created_at' => $reply->created_at->toISOString(),
                        ];
                    }),
                ];
            });

        return response()->json([
            'success' => true,
            'tickets' => $tickets,
        ]);
    }

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
            'priority' => 'nullable|in:low,normal,high',
            'category' => 'nullable|string|max:50',
            'tags' => 'nullable|string|max:500',
            'attachments' => 'nullable|array',
            'attachments.*.name' => 'required_with:attachments|string',
            'attachments.*.path' => 'required_with:attachments|string',
            'attachments.*.size' => 'nullable|integer',
            'attachments.*.mime' => 'nullable|string',
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
        if (!$site) {
            return response()->json(['success' => false, 'error' => 'site_not_found'], 403);
        }
        if ($site->support_expires_at !== null && !$this->supportLicenseService->canAccessSupport($site)) {
            return response()->json([
                'success' => false,
                'error' => 'support_expired',
                'message' => 'دسترسی به پشتیبانی منقضی شده یا هنوز شروع نشده است.',
            ], 403);
        }

        $ticket = $this->supportTicketService->create([
            'site_id' => $siteId,
            'subject' => $request->subject,
            'message' => $request->message,
            'customer_email' => $request->customer_email,
            'customer_name' => $request->customer_name,
            'priority' => $request->input('priority', 'normal'),
            'category' => $request->category,
            'tags' => $request->tags,
            'attachments' => $request->attachments,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تیکت با موفقیت ثبت شد.',
            'ticket_number' => $ticket->ticket_number,
        ]);
    }

    /**
     * POST /api/support-tickets/{id}/reply
     * ارسال پاسخ به یک تیکت خاص از سمت مشتری
     */
    public function reply(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string',
            'domain' => 'required|string',
            'message' => 'required|string|max:5000',
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

        $ticket = SupportTicket::where('id', $id)->where('site_id', $siteId)->first();
        if (!$ticket) {
            return response()->json([
                'success' => false,
                'error' => 'ticket_not_found',
                'message' => 'تیکت مورد نظر یافت نشد.',
            ], 404);
        }

        $ticket = $this->supportTicketService->addReply($ticket, [
            'message' => $request->message,
            'author_type' => 'customer',
            'customer_name' => $request->customer_name ?? 'مشتری',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'پاسخ شما با موفقیت ثبت شد.',
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'status' => $ticket->status,
                'replies' => $ticket->replies->map(function ($reply) {
                    return [
                        'id' => $reply->id,
                        'message' => $reply->message,
                        'author_type' => $reply->author_type,
                        'customer_name' => $reply->customer_name,
                        'created_at' => $reply->created_at->toISOString(),
                    ];
                }),
            ],
        ]);
    }

    /**
     * GET /api/support-tickets/unread-count?license_key=...&domain=...
     * دریافت تعداد تیکت‌های با پاسخ جدید (برای نمایش نشانگر)
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $request->validate([
            'license_key' => 'required|string',
            'domain' => 'required|string',
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

        // تیکت‌هایی که وضعیت open یا in_progress دارند و حداقل یک پاسخ از سمت staff دارند
        $count = SupportTicket::where('site_id', $siteId)
            ->whereIn('status', ['open', 'in_progress'])
            ->whereHas('replies', function ($q) {
                $q->where('author_type', 'staff');
            })
            ->count();

        return response()->json([
            'success' => true,
            'unread_count' => $count,
        ]);
    }
}
