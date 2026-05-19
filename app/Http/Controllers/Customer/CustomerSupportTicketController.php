<?php

namespace App\Http\Controllers\Customer;

use App\Helpers\CustomerMenu;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Services\SupportLicenseService;
use Illuminate\Http\Request;

/**
 * پنل تیکت‌های پشتیبانی برای مشتریان (کاربران غیرادمین).
 * مشتری می‌تواند تیکت‌های سایت‌های خود را مشاهده، ثبت و پاسخ دهد.
 */
class CustomerSupportTicketController extends Controller
{
    public function __construct(
        protected SupportLicenseService $supportLicenseService
    ) {}

    /**
     * نمایش لیست تیکت‌های مشتری (فقط سایت‌های خودش).
     */
    public function index(Request $request)
    {
        if (!CustomerMenu::isVisible('support-tickets')) {
            abort(403, 'دسترسی به این بخش غیرفعال شده است.');
        }

        $user = $request->user();
        $customer = $user->customer;
        $siteIds = $customer ? $customer->sites()->pluck('id') : collect();

        $query = SupportTicket::with(['site', 'assignee'])
            ->whereIn('site_id', $siteIds)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tickets = $query->paginate(20);

        return view('customer.support-tickets.index', compact('tickets', 'customer'));
    }

    /**
     * فرم ثبت تیکت جدید (سایت‌های مشتری).
     */
    public function create(Request $request)
    {
        if (!CustomerMenu::isVisible('support-tickets')) {
            abort(403, 'دسترسی به این بخش غیرفعال شده است.');
        }

        $user = $request->user();
        $customer = $user->customer;
        $sites = $customer ? $customer->sites()->where('is_active', true)->orderBy('title')->get(['id', 'title', 'domain']) : collect();

        return view('customer.support-tickets.create', compact('sites', 'customer'));
    }

    /**
     * ذخیره تیکت جدید.
     */
    public function store(Request $request)
    {
        if (!CustomerMenu::isVisible('support-tickets')) {
            abort(403, 'دسترسی به این بخش غیرفعال شده است.');
        }

        $user = $request->user();
        $customer = $user->customer;
        $siteIds = $customer ? $customer->sites()->pluck('id')->toArray() : [];

        $data = $request->validate([
            'site_id' => 'required|in:' . implode(',', $siteIds),
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'priority' => 'nullable|in:low,normal,high',
        ]);

        $data['priority'] = $data['priority'] ?? 'normal';
        $data['customer_email'] = $user->email ?? '';
        $data['customer_name'] = $user->name ?? $user->email;
        $data['status'] = SupportTicket::STATUS_OPEN;

        $site = \App\Models\Site::find($data['site_id']);
        $data['support_level'] = $site ? $this->supportLicenseService->getLevel($site) : 'basic';

        SupportTicket::create($data);

        return redirect()->route('customer.support-tickets.index')
            ->with('success', 'تیکت شما با موفقیت ثبت شد. در اسرع وقت پاسخ داده خواهد شد.');
    }

    /**
     * نمایش جزئیات تیکت (فقط تیکت‌های خود مشتری).
     */
    public function show(Request $request, SupportTicket $supportTicket)
    {
        if (!CustomerMenu::isVisible('support-tickets')) {
            abort(403, 'دسترسی به این بخش غیرفعال شده است.');
        }

        $user = $request->user();
        $customer = $user->customer;
        $siteIds = $customer ? $customer->sites()->pluck('id')->toArray() : [];

        // امنیت: فقط تیکت‌های متعلق به سایت‌های خود مشتری
        if (!in_array($supportTicket->site_id, $siteIds)) {
            abort(403);
        }

        $supportTicket->load(['site', 'assignee', 'replies.user']);

        return view('customer.support-tickets.show', compact('supportTicket'));
    }

    /**
     * ثبت پاسخ از طرف مشتری در تیکت.
     */
    public function storeReply(Request $request, SupportTicket $supportTicket)
    {
        if (!CustomerMenu::isVisible('support-tickets')) {
            abort(403, 'دسترسی به این بخش غیرفعال شده است.');
        }

        $user = $request->user();
        $customer = $user->customer;
        $siteIds = $customer ? $customer->sites()->pluck('id')->toArray() : [];

        // امنیت: فقط تیکت‌های متعلق به سایت‌های خود مشتری
        if (!in_array($supportTicket->site_id, $siteIds)) {
            abort(403);
        }

        // فقط تیکت‌های باز یا در حال بررسی قابل پاسخ هستند
        if ($supportTicket->status === SupportTicket::STATUS_CLOSED) {
            return back()->with('error', 'این تیکت بسته شده است و قابل پاسخ‌دهی نیست.');
        }

        $data = $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        SupportTicketReply::create([
            'support_ticket_id' => $supportTicket->id,
            'user_id' => $user->id,
            'message' => $data['message'],
            'author_type' => SupportTicketReply::AUTHOR_CUSTOMER,
            'customer_email' => $user->email,
            'customer_name' => $user->name,
        ]);

        return back()->with('success', 'پاسخ شما ثبت شد.');
    }
}
