<?php

/**
 * @deprecated PHASE 7.1 — Use SaaSPlatform\Modules\Tickets\Http\Controllers\Admin\TicketController instead.
 *             This controller operates on the legacy support_tickets table and App\Models\SupportTicket.
 */

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignSupportTicketRequest;
use App\Http\Requests\Admin\StoreSupportTicketReplyRequest;
use App\Http\Requests\Admin\StoreSupportTicketRequest;
use App\Http\Requests\Admin\UpdateSupportTicketStatusRequest;
use App\Models\Site;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function __construct(
        protected SupportTicketService $supportTicketService
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only(['status', 'priority', 'site_id', 'category', 'q']);
        $tickets = $this->supportTicketService->getAll($filters);
        return view('admin.support-tickets.index', compact('tickets'));
    }

    public function create()
    {
        $sites = Site::where('is_active', true)->orderBy('title')->get(['id', 'title', 'domain']);
        return view('admin.support-tickets.create', compact('sites'));
    }

    public function store(StoreSupportTicketRequest $request)
    {
        $this->supportTicketService->create(
            $request->validated(),
            $request->file('attachments')
        );

        return redirect()->route('admin.support-tickets.index')->with('success', 'تیکت با موفقیت ثبت شد.');
    }

    public function show(SupportTicket $supportTicket)
    {
        $supportTicket->load(['site', 'assignee', 'replies.user']);
        return view('admin.support-tickets.show', compact('supportTicket'));
    }

    public function updateStatus(UpdateSupportTicketStatusRequest $request, SupportTicket $supportTicket)
    {
        $this->supportTicketService->updateStatus($supportTicket, $request->validated()['status']);
        return back()->with('success', 'وضعیت تیکت با موفقیت به‌روزرسانی شد.');
    }

    public function storeReply(StoreSupportTicketReplyRequest $request, SupportTicket $supportTicket)
    {
        $this->supportTicketService->addReply($supportTicket, [
            'user_id' => $request->user()->id,
            'message' => $request->validated()['message'],
            'author_type' => \App\Models\SupportTicketReply::AUTHOR_STAFF,
        ], $request->file('attachments'));

        return back()->with('success', 'پاسخ با موفقیت ثبت شد.');
    }

    public function assign(AssignSupportTicketRequest $request, SupportTicket $supportTicket)
    {
        $this->supportTicketService->assignTo($supportTicket, $request->input('assigned_to'));
        return back()->with('success', 'تیکت با موفقیت به کارشناس ارجاع شد.');
    }
}
