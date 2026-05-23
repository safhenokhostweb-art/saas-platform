<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Services\TicketService;

class TicketsController extends Controller
{
    public function __construct(
        protected TicketService $ticketService,
        protected TenantContext $tenantContext
    ) {}

    /**
     * Display a listing of tickets.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['status', 'priority', 'site_id', 'category', 'q']);
        $tickets = $this->ticketService->getAll($filters);

        // بررسی feature flag برای نمایش بخش SLA در ویو
        $slaEnabled = $this->tenantContext->hasFeature('tickets.sla');

        // بررسی feature flag برای نمایش دکمهٔ auto-assign
        $autoAssignEnabled = $this->tenantContext->hasFeature('tickets.auto_assign');

        return view('tickets::index', compact(
            'tickets',
            'slaEnabled',
            'autoAssignEnabled'
        ));
    }

    /**
     * Show the form for creating a new ticket.
     */
    public function create()
    {
        $sites = \App\Models\Site::where('is_active', true)->orderBy('title')->get(['id', 'title', 'domain']);

        // بررسی feature flag برای آپلود فایل ضمیمه
        $attachmentsEnabled = app(TenantContext::class)->hasFeature('tickets.attachments');

        return view('tickets::create', compact('sites', 'attachmentsEnabled'));
    }

    /**
     * Store a newly created ticket.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'nullable|in:low,normal,high',
            'site_id' => 'nullable|exists:sites,id',
            'customer_email' => 'nullable|email|max:255',
            'customer_name' => 'nullable|string|max:255',
            'category' => 'nullable|string|max:255',
        ]);

        // فقط در صورت فعال بودن feature، فایل ضمیمه پردازش شود
        $attachments = $this->tenantContext->hasFeature('tickets.attachments')
            ? $request->file('attachments')
            : null;

        $this->ticketService->create($validated, $attachments);

        return redirect()->route('admin.tickets.index')->with('success', 'تیکت با موفقیت ثبت شد.');
    }

    /**
     * Display the specified ticket.
     */
    public function show(Ticket $ticket)
    {
        $ticket->load(['site', 'assignee', 'replies.user']);

        // بررسی feature flag برای نمایش SLA
        $slaEnabled = $this->tenantContext->hasFeature('tickets.sla');

        return view('tickets::show', compact('ticket', 'slaEnabled'));
    }

    /**
     * Update the status of a ticket.
     */
    public function updateStatus(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,closed',
        ]);

        $this->ticketService->updateStatus($ticket, $validated['status']);

        return back()->with('success', 'وضعیت تیکت با موفقیت به‌روزرسانی شد.');
    }

    /**
     * Store a reply for a ticket.
     */
    public function storeReply(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        $this->ticketService->addReply($ticket, [
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'author_type' => \SaaSPlatform\Modules\Tickets\Models\TicketReply::AUTHOR_STAFF,
        ], $request->file('attachments'));

        return back()->with('success', 'پاسخ با موفقیت ثبت شد.');
    }

    /**
     * Assign a ticket to a user.
     */
    public function assign(Request $request, Ticket $ticket)
    {
        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $this->ticketService->assignTo($ticket, $validated['assigned_to']);

        return back()->with('success', 'تیکت با موفقیت به کارشناس ارجاع شد.');
    }
}
