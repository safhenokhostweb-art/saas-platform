<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Services\SupportLicenseService;
use Illuminate\Http\Request;

class SupportTicketController extends Controller
{
    public function __construct(
        protected SupportLicenseService $supportLicenseService
    ) {}

    public function create()
    {
        $sites = Site::where('is_active', true)->orderBy('title')->get(['id', 'title', 'domain']);
        return view('admin.support-tickets.create', compact('sites'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'site_id' => 'required|exists:sites,id',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'customer_email' => 'required|email',
            'customer_name' => 'nullable|string|max:255',
            'priority' => 'nullable|in:low,normal,high',
        ]);
        $data['priority'] = $data['priority'] ?? 'normal';

        $site = Site::find($data['site_id']);
        $data['support_level'] = $site ? $this->supportLicenseService->getLevel($site) : 'basic';

        SupportTicket::create($data);
        return redirect()->route('admin.support-tickets.index')->with('success', 'تیکت ثبت شد.');
    }

    public function index(Request $request)
    {
        $query = SupportTicket::with(['site', 'assignee'])->latest();
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        $tickets = $query->paginate(20);
        return view('admin.support-tickets.index', compact('tickets'));
    }

    public function show(SupportTicket $supportTicket)
    {
        $supportTicket->load(['site', 'assignee', 'replies.user']);
        return view('admin.support-tickets.show', compact('supportTicket'));
    }

    public function updateStatus(Request $request, SupportTicket $supportTicket)
    {
        $request->validate(['status' => 'required|in:open,in_progress,closed']);
        $updates = [
            'status' => $request->status,
            'closed_at' => $request->status === 'closed' ? now() : null,
        ];
        if ($request->status === 'closed') {
            $updates['resolved_at'] = now();
        }
        $supportTicket->update($updates);
        return back()->with('success', 'وضعیت به‌روزرسانی شد.');
    }

    public function storeReply(Request $request, SupportTicket $supportTicket)
    {
        $data = $request->validate(['message' => 'required|string|max:5000']);

        SupportTicketReply::create([
            'support_ticket_id' => $supportTicket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'author_type' => SupportTicketReply::AUTHOR_STAFF,
        ]);

        if (!$supportTicket->first_response_at) {
            $supportTicket->update(['first_response_at' => now()]);
        }
        if ($supportTicket->status === SupportTicket::STATUS_OPEN) {
            $supportTicket->update(['status' => SupportTicket::STATUS_IN_PROGRESS]);
        }

        return back()->with('success', 'پاسخ ثبت شد.');
    }

    public function assign(Request $request, SupportTicket $supportTicket)
    {
        $assignedTo = $request->input('assigned_to') ?: null;
        $request->validate([
            'assigned_to' => $assignedTo ? 'required|exists:users,id' : 'nullable',
        ]);
        $supportTicket->update(['assigned_to' => $assignedTo]);
        return back()->with('success', 'تیکت به‌روزرسانی شد.');
    }
}
