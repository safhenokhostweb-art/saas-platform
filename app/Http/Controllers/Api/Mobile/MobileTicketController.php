<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketReply;

/**
 * SP-2.0 — Mobile Ticket API
 *
 * جداسازی تیکت‌ها بر اساس نقش:
 * - ادمین اصلی (is_admin=true, site_id=null): فقط تیکت‌های بدون site (پلتفرمی)
 * - ادمین سایت مشتری (is_admin=false, site_id=X): تمام تیکت‌های site خودش
 * - کاربر عادی (is_admin=false, site_id=X): فقط تیکت‌هایی که با موبایل خودش ثبت شده
 */
class MobileTicketController extends Controller
{
    public function __construct(
        private readonly TicketServiceInterface $ticketService
    ) {}

    // ── GET /api/mobile/tickets ────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $role   = $this->getUserRole($user);
        $filters = $this->buildFilters($request, $user, $role);

        // site_id=null باید حفظ شود (تیکت‌های پلتفرمی بدون site)
        $cleanFilters = array_filter(
            $filters,
            fn($v, $k) => $k === 'site_id' || ($v !== null && $v !== ''),
            ARRAY_FILTER_USE_BOTH
        );

        $tickets = $this->ticketService->getAll($cleanFilters);

        return response()->json([
            'data' => $tickets->getCollection()->map(fn(Ticket $t) => $this->ticketSummary($t))->values(),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page'    => $tickets->lastPage(),
                'total'        => $tickets->total(),
                'per_page'     => $tickets->perPage(),
                'role'         => $role,
            ],
        ]);
    }

    // ── GET /api/mobile/tickets/{id} ───────────────────────────────────────

    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = $this->ticketService->getById($id);
        $user   = $request->user();

        if (! $ticket || ! $this->canAccessTicket($user, $ticket)) {
            return response()->json(['message' => 'تیکت یافت نشد.'], 404);
        }

        return response()->json(['data' => $this->ticketDetail($ticket)]);
    }

    // ── POST /api/mobile/tickets ───────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $this->getUserRole($user);

        $rules = [
            'subject'     => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority'    => ['nullable', 'in:low,normal,high'],
            'category'    => ['nullable', 'string', 'max:100'],
        ];

        // ادمین سایت می‌تواند از طرف مشتری تیکت بزند
        if ($role === 'site_admin') {
            $rules['customer_mobile'] = ['required', 'string', 'regex:/^09[0-9]{9}$/'];
            $rules['customer_name']   = ['nullable', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);

        $ticket = $this->ticketService->create([
            'subject'         => $validated['subject'],
            'description'     => $validated['description'],
            'customer_mobile' => match($role) {
                'site_admin' => $validated['customer_mobile'] ?? $user->mobile,
                default      => $user->mobile,
            },
            'customer_name'   => match($role) {
                'site_admin' => $validated['customer_name'] ?? $user->mobile,
                default      => $user->mobile,
            },
            'priority'        => $validated['priority'] ?? Ticket::PRIORITY_NORMAL,
            'category'        => $validated['category'] ?? null,
            'site_id'         => $user->site_id, // همیشه site کاربر
        ]);

        return response()->json([
            'data'    => $this->ticketDetail($ticket),
            'message' => 'تیکت با موفقیت ثبت شد.',
        ], 201);
    }

    // ── POST /api/mobile/tickets/{id}/reply ────────────────────────────────

    public function reply(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $ticket = $this->ticketService->getById($id);

        if (! $ticket || ! $this->canAccessTicket($user, $ticket)) {
            return response()->json(['message' => 'تیکت یافت نشد.'], 404);
        }

        $validated = $request->validate([
            'message'     => ['required', 'string'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $role = $this->getUserRole($user);

        $ticket = $this->ticketService->addReply($ticket, [
            'message'       => $validated['message'],
            'author_type'   => $role === 'end_user' ? TicketReply::AUTHOR_CUSTOMER : TicketReply::AUTHOR_STAFF,
            'user_id'       => $user->id,
            'customer_name' => $user->mobile ?? ($role === 'end_user' ? 'مشتری' : 'پشتیبان'),
            'is_internal'   => $validated['is_internal'] ?? false,
        ]);

        return response()->json([
            'data'    => $this->ticketDetail($ticket),
            'message' => 'پاسخ ثبت شد.',
        ]);
    }

    // ── PATCH /api/mobile/tickets/{id}/status ─────────────────────────────

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $ticket = $this->ticketService->getById($id);

        if (! $ticket || ! $this->canAccessTicket($user, $ticket)) {
            return response()->json(['message' => 'تیکت یافت نشد.'], 404);
        }

        // فقط ادمین‌ها می‌توانند وضعیت را تغییر دهند
        $role = $this->getUserRole($user);
        if ($role === 'end_user') {
            return response()->json(['message' => 'دسترسی کافی نیست.'], 403);
        }

        $validated = $request->validate([
            'status' => ['required', 'in:open,in_progress,closed'],
        ]);

        $ticket = $this->ticketService->updateStatus($ticket, $validated['status']);

        return response()->json([
            'data'    => $this->ticketSummary($ticket),
            'message' => 'وضعیت تیکت بروز شد.',
        ]);
    }

    // ── POST /api/mobile/tickets/{id}/attachments ─────────────────────────

    public function uploadAttachment(Request $request, int $id): JsonResponse
    {
        $user   = $request->user();
        $ticket = $this->ticketService->getById($id);

        if (! $ticket || ! $this->canAccessTicket($user, $ticket)) {
            return response()->json(['message' => 'تیکت یافت نشد.'], 404);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,zip,txt'],
        ]);

        $file     = $request->file('file');
        $filename = $file->getClientOriginalName();
        $size     = $file->getSize();
        $mime     = $file->getMimeType();

        $targetDir = storage_path('app' . DIRECTORY_SEPARATOR . 'private'
            . DIRECTORY_SEPARATOR . 'ticket-attachments'
            . DIRECTORY_SEPARATOR . $ticket->id);

        if (! is_dir($targetDir)) mkdir($targetDir, 0755, true);

        $storedName = now()->format('YmdHis') . '_' . $file->hashName();
        $file->move($targetDir, $storedName);
        $path = "ticket-attachments/{$ticket->id}/{$storedName}";

        $attachments   = $ticket->attachments ?? [];
        $attachments[] = ['name' => $filename, 'path' => $path, 'size' => $size, 'mime' => $mime];
        $ticket->update(['attachments' => $attachments]);

        \DB::table('ticket_attachments')->insert([
            'ticket_id'           => $ticket->id,
            'original_name'       => $filename,
            'stored_path'         => $path,
            'mime_type'           => $mime,
            'size_bytes'          => $size,
            'disk'                => 'private',
            'uploaded_by_type'    => $this->getUserRole($user) === 'end_user' ? 'customer' : 'staff',
            'uploaded_by_user_id' => $user->id,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return response()->json(['data' => compact('filename', 'path', 'size', 'mime')], 201);
    }

    // ── GET /api/mobile/tickets/{id}/attachments/{path} ───────────────────

    public function downloadAttachment(Request $request, int $id, string $path): mixed
    {
        $user   = $request->user();
        $ticket = $this->ticketService->getById($id);

        if (! $ticket || ! $this->canAccessTicket($user, $ticket)) {
            return response()->json(['message' => 'تیکت یافت نشد.'], 404);
        }

        $safePath = ltrim($path, '/');
        if (str_contains($safePath, '..')) abort(403);

        $abs = storage_path('app' . DIRECTORY_SEPARATOR . 'private'
            . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safePath));

        if (! file_exists($abs)) abort(404);

        return response()->download($abs);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * نقش کاربر را برمی‌گرداند:
     * - platform_admin: ادمین اصلی پلتفرم
     * - site_admin: ادمین سایت مشتری
     * - end_user: کاربر نهایی
     */
    private function getUserRole($user): string
    {
        if ($user->is_admin)       return 'platform_admin';
        if ($user->is_site_admin)  return 'site_admin';
        return 'end_user';
    }

    /**
     * فیلترهای query را بر اساس نقش می‌سازد
     */
    private function buildFilters(Request $request, $user, string $role): array
    {
        $base = [
            'status'   => $request->query('status'),
            'priority' => $request->query('priority'),
            'search'   => $request->query('search'),
            'per_page' => min((int) $request->query('per_page', 20), 50),
        ];

        return match($role) {
            // ادمین اصلی: فقط تیکت‌های بدون site (پلتفرمی) یا تیکت‌هایی که assigned_to اوست
            'platform_admin' => array_merge($base, [
                'site_id' => null,  // در filter یعنی تیکت‌هایی که site_id ندارند
            ]),

            // ادمین سایت: تمام تیکت‌های سایت خودش
            'site_admin' => array_merge($base, [
                'site_id' => $user->site_id,
                'assigned' => $request->query('assigned') === 'me' ? $user->id : null,
            ]),

            // کاربر عادی: فقط تیکت‌های خودش در سایت خودش
            default => array_merge($base, [
                'customer_mobile' => $user->mobile,
                'site_id'         => $user->site_id,
            ]),
        };
    }

    /**
     * آیا کاربر به این تیکت دسترسی دارد؟
     */
    private function canAccessTicket($user, Ticket $ticket): bool
    {
        $role = $this->getUserRole($user);

        return match($role) {
            'platform_admin' => $ticket->site_id === null,
            'site_admin'     => $ticket->site_id == $user->site_id,
            'end_user'       => $ticket->customer_mobile === $user->mobile
                                && $ticket->site_id == $user->site_id,
        };
    }

    // ─── Payload helpers ─────────────────────────────────────────────────────

    private function ticketSummary(Ticket $ticket): array
    {
        return [
            'id'              => $ticket->id,
            'ticket_number'   => $ticket->ticket_number,
            'subject'         => $ticket->subject,
            'status'          => $ticket->status,
            'priority'        => $ticket->priority,
            'category'        => $ticket->category,
            'customer_name'   => $ticket->customer_name,
            'customer_mobile' => $ticket->customer_mobile,
            'assignee_name'   => $ticket->assignee?->mobile,
            'created_at'      => $ticket->created_at?->toIso8601String(),
            'updated_at'      => $ticket->updated_at?->toIso8601String(),
        ];
    }

    private function ticketDetail(Ticket $ticket): array
    {
        return array_merge($this->ticketSummary($ticket), [
            'description'       => $ticket->description,
            'tags'              => $ticket->tags,
            'attachments'       => $ticket->attachments ?? [],
            'closed_at'         => $ticket->closed_at?->toIso8601String(),
            'first_response_at' => $ticket->first_response_at?->toIso8601String(),
            'resolved_at'       => $ticket->resolved_at?->toIso8601String(),
            'replies'           => $ticket->relationLoaded('replies')
                ? $ticket->replies->map(fn(TicketReply $r) => $this->replyPayload($r))->values()
                : [],
        ]);
    }

    private function replyPayload(TicketReply $reply): array
    {
        return [
            'id'          => $reply->id,
            'message'     => $reply->message,
            'author_type' => $reply->author_type,
            'author_name' => $reply->customer_name ?? ($reply->author_type === 'staff' ? 'پشتیبان' : 'مشتری'),
            'is_internal' => (bool) ($reply->is_internal ?? false),
            'attachments' => $reply->attachments ?? [],
            'created_at'  => $reply->created_at?->toIso8601String(),
        ];
    }
}
