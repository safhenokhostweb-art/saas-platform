<?php
namespace App\Http\Controllers\Customer;

use App\Helpers\CustomerMenu;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerReplyRequest;
use App\Http\Requests\Customer\StoreCustomerSupportTicketRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Services\SupportTicketService;
use Illuminate\Http\Request;

/**
 * پنل تیکت‌های پشتیبانی برای مشتریان (کاربران غیرادمین).
 * مشتری می‌تواند تیکت‌های سایت‌های خود را مشاهده، ثبت و پاسخ دهد.
 */
class CustomerSupportTicketController extends Controller
{
    public function __construct(
        protected SupportTicketService $supportTicketService
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
        $siteIds = $customer ? $customer->sites()->pluck('id')->toArray() : [];

        $tickets = $this->supportTicketService->getAll([
            'site_ids' => $siteIds,
            'status' => $request->status,
        ]);

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
    public function store(StoreCustomerSupportTicketRequest $request)
    {
        if (!CustomerMenu::isVisible('support-tickets')) {
            abort(403, 'دسترسی به این بخش غیرفعال شده است.');
        }

        $user = $request->user();

        $data = $request->validated();
        $data['customer_email'] = $user->email ?? '';
        $data['customer_name'] = $user->name ?? $user->email;
        $data['status'] = SupportTicket::STATUS_OPEN;

        $this->supportTicketService->create($data);

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
    public function storeReply(StoreCustomerReplyRequest $request, SupportTicket $supportTicket)
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

        $this->supportTicketService->addReply($supportTicket, [
            'user_id' => $user->id,
            'message' => $request->validated()['message'],
            'author_type' => SupportTicketReply::AUTHOR_CUSTOMER,
            'customer_email' => $user->email,
            'customer_name' => $user->name,
        ]);

        return back()->with('success', 'پاسخ شما با موفقیت ثبت شد.');
    }
}
