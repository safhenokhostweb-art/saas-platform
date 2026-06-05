<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface;
use SaaSPlatform\Modules\Tickets\Services\TicketGateService;
use SaaSPlatform\Modules\Tickets\Services\TicketSlaService;
use SaaSPlatform\Modules\Tickets\Services\TicketNotificationService;
use SaaSPlatform\Modules\Tickets\Http\Requests\Admin\StoreTicketRequest;
use SaaSPlatform\Modules\Tickets\Services\TicketTimelineService;
use SaaSPlatform\Modules\Tickets\Models\Ticket;
use SaaSPlatform\Modules\Tickets\Models\TicketActivity;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\Setting;

/**
 * PHASE 7.1 — TASK 3: Admin Ticket Controller (canonical)
 *
 * Handles index, create, store, show for admin context.
 * Status/assignment/SLA are split into dedicated controllers.
 */
class TicketController extends Controller
{
    use AuthorizesRequests;
    public function __construct(
        protected TicketServiceInterface     $ticketService,
        protected TicketGateService          $gateService,
        protected TicketSlaService           $slaService,
        protected SiteRepositoryInterface    $siteRepository,
        protected TicketNotificationService  $notificationService,
        protected TicketTimelineService      $timeline,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', \SaaSPlatform\Modules\Tickets\Models\Ticket::class);

        $user    = $request->user();
        $filters = $request->only(['status', 'priority', 'site_id', 'category', 'type', 'q', 'sort_by', 'sort_dir', 'per_page']);

        // platform admin = همه تیکت‌های سیستم (super admin)
        // site admin = فقط تیکت‌های سایت خودش
        if ($user->is_site_admin && !$user->is_admin) {
            $filters['site_id'] = $user->site_id;
        }
        // platform admin: بدون فیلتر site_id — همه تیکت‌ها را می‌بیند

        $tickets = $this->ticketService->getAll($filters);

        // آخرین پاسخ عمومی هر تیکت — نویسنده + پیام کوتاه + زمان
        // برای سیستم توجه رنگی (Section F — SUPPORT-SUITE-01)
        $lastReplies = \SaaSPlatform\Modules\Tickets\Models\TicketReply::query()
            ->selectRaw('ticket_id, id, author_type, LEFT(message, 80) as preview, created_at')
            ->whereIn('ticket_id', $tickets->pluck('id'))
            ->where('is_internal', false)
            ->orderByDesc('created_at')
            ->get()
            ->unique('ticket_id')
            ->keyBy('ticket_id');

        $lastReplyAuthors = $lastReplies->pluck('author_type', 'ticket_id');

        $stats   = ($user->is_admin && !$user->is_site_admin)
            ? $this->ticketService->getStats()          // همه تیکت‌ها
            : $this->ticketService->getStats(siteId: $user->site_id);

        // تعداد درخواست‌های ویژگی باز — برای badge در تب فیلتر
        $frOpenCount = \SaaSPlatform\Modules\Tickets\Models\Ticket::withoutTenantIsolation()
            ->where('type', 'feature_request')
            ->whereIn('status', ['open', 'in_progress'])
            ->when($user->is_site_admin && !$user->is_admin, fn($q) => $q->where('site_id', $user->site_id))
            ->count();

        return view('tickets::admin.tickets.index', [
            'tickets'          => $tickets,
            'stats'            => $stats,
            'filters'          => $filters,
            'slaEnabled'       => $this->gateService->isSlaEnabled(),
            'lastReplyAuthors' => $lastReplyAuthors,
            'lastReplies'      => $lastReplies,
            'frOpenCount'      => $frOpenCount,
        ]);
    }

    public function create()
    {
        $this->authorize('create', \SaaSPlatform\Modules\Tickets\Models\Ticket::class);

        $userModel   = config('tickets.user_model', \App\Models\User::class);
        $authUser    = auth()->user();
        $customers   = \App\Models\Customer::orderBy('name')->get(['id', 'name', 'mobile', 'user_id']);
        $sites       = $this->getActiveSites();

        // کاربرانی که customer هم هستند فقط یک‌بار در لیست نشان داده می‌شوند
        $customerUserIds  = $customers->pluck('user_id')->filter()->toArray();
        $customerMobiles  = $customers->pluck('mobile')->filter()->toArray();

        // ادمین پلتفرم فقط ادمین/سایت‌ادمین می‌بیند — ادمین سایت فقط اعضای سایت خودش
        $usersQuery = $userModel::orderBy('mobile')
            ->whereNotIn('id', $customerUserIds)
            ->whereNotIn('mobile', $customerMobiles); // جلوگیری از نمایش دوباره کسانی که customer هم هستند
        if ($authUser->is_admin && !$authUser->is_site_admin) {
            $usersQuery->where(fn($q) => $q->where('is_admin', true)->orWhere('is_site_admin', true));
        } elseif ($authUser->is_site_admin) {
            $usersQuery->where('site_id', $authUser->site_id);
        }
        $users = $usersQuery->get(['id', 'mobile', 'is_admin', 'is_site_admin']);

        $people = $users->map(fn($u) => [
            'key'         => 'u_' . $u->id,
            'type'        => 'user',
            'user_id'     => $u->id,
            'customer_id' => null,
            'name'        => $u->name ?? $u->mobile ?? '',
            'mobile'      => $u->mobile ?? '',
            'badge'       => $u->is_admin && !$u->is_site_admin
                                ? 'ادمین پلتفرم'
                                : ($u->is_site_admin ? 'ادمین سایت' : 'کاربر عادی'),
        ])->merge(
            $customers->map(function ($c) use ($userModel) {
                // اگر customer یک user با نقش ادمین سایت دارد، badge را صحیح نشان بده
                $linkedUser = $c->user_id ? $userModel::find($c->user_id) : null;
                $badge = 'مشتری';
                if ($linkedUser?->is_site_admin) {
                    $badge = 'ادمین سایت';
                } elseif ($linkedUser?->is_admin) {
                    $badge = 'ادمین پلتفرم';
                }
                return [
                    'key'         => 'c_' . $c->id,
                    'type'        => 'customer',
                    'user_id'     => $c->user_id,
                    'customer_id' => $c->id,
                    'name'        => $c->name ?? $c->mobile ?? '',
                    'mobile'      => $c->mobile ?? '',
                    'badge'       => $badge,
                ];
            })
        )->values();

        return view('tickets::admin.tickets.create', [
            'people'              => $people,
            'sites'               => $sites,
            'attachmentsEnabled'  => $this->gateService->canUploadAttachments(),
            'autoAssignEnabled'   => $this->gateService->isAutoAssignEnabled(),
        ]);
    }

    public function store(StoreTicketRequest $request)
    {
        $this->authorize('create', \SaaSPlatform\Modules\Tickets\Models\Ticket::class);

        $data = $request->validated();

        $customerSiteId = null; // site_id سایت مشتری انتخاب‌شده

        if (!empty($data['for_customer_id'])) {
            $customer = \App\Models\Customer::with('sites')->find($data['for_customer_id']);
            if ($customer) {
                $data['customer_id']    = $customer->user_id ?? null;
                $data['customer_name']   = $data['customer_name']   ?? $customer->name;
                $data['customer_mobile'] = $data['customer_mobile'] ?? $customer->mobile;
                // اگر مشتری سایت دارد، تیکت باید به سایت او تعلق داشته باشد تا از طریق API دریافت شود
                $customerSiteId = $customer->sites()->first()?->id;
            }
            unset($data['for_customer_id']);
        } elseif (!empty($data['for_user_id'])) {
            $userModel = config('tickets.user_model', \App\Models\User::class);
            $user = $userModel::find($data['for_user_id']);
            if ($user) {
                $data['customer_id']     = $user->id;
                $data['customer_name']   = $data['customer_name']   ?? $user->name;
                $data['customer_mobile'] = $data['customer_mobile'] ?? $user->mobile;
                // اگر کاربر site_id دارد، تیکت به سایت او تعلق دارد
                $customerSiteId = $user->site_id ?? null;
            }
        }
        unset($data['for_user_id'], $data['for_customer_id']);

        $files = $this->gateService->canUploadAttachments()
            ? array_filter((array) $request->file('attachments'))
            : null;
        $files = empty($files) ? null : array_values($files);

        $authUser = $request->user();
        if ($customerSiteId) {
            // تیکت برای مشتری خاص → site_id سایت آن مشتری تا از طریق tenant API دریافت شود
            $data['site_id'] = $customerSiteId;
        } elseif ($authUser->is_admin && !$authUser->is_site_admin) {
            // تیکت عمومی ادمین پلتفرم بدون مشتری خاص → پلتفرمی
            $data['site_id'] = $data['site_id'] ?? null;
        } elseif ($authUser->is_site_admin && empty($data['site_id'])) {
            $data['site_id'] = $authUser->site_id;
        }
        $data['status'] = $data['status'] ?? \SaaSPlatform\Modules\Tickets\Models\Ticket::STATUS_OPEN;

        $ticket = $this->ticketService->create($data, $files);

        $this->notificationService->onCreated($ticket);

        return redirect()
            ->route('tickets.admin.index')
            ->with('success', 'تیکت با موفقیت ایجاد شد.');
    }

    public function show(int $id)
    {
        $ticket = $this->ticketService->getById($id);

        if (!$ticket) {
            abort(404);
        }

        $this->authorize('view', $ticket);

        $userModel  = config('tickets.user_model', \App\Models\User::class);
        $adminUsers = $userModel::where('is_admin', true)->with('profile')->get(['id', 'mobile']);
        $sites      = $this->siteRepository->allActive();
        $smsEnabled = (bool) Setting::get('tickets.sms_enabled', false);

        $activities    = TicketActivity::where('ticket_id', $ticket->id)
            ->orderBy('created_at', 'asc')
            ->get();
        $internalNotes = $ticket->internalNotes()->get();

        $sidebarUser = auth()->user();
        $sidebarQuery = Ticket::withoutTenantIsolation()
            ->select(['id', 'ticket_number', 'subject', 'status', 'priority', 'customer_name', 'updated_at']);
        // platform admin: همه تیکت‌ها در sidebar
        if ($sidebarUser->is_site_admin && !$sidebarUser->is_admin) {
            $sidebarQuery->where('site_id', $sidebarUser->site_id);
        }
        $sidebarTickets = $sidebarQuery->orderByDesc('updated_at')->limit(60)->get();

        return view('tickets::admin.tickets.show', [
            'ticket'             => $ticket,
            'adminUsers'         => $adminUsers,
            'sites'              => $sites,
            'smsEnabled'         => $smsEnabled,
            'slaEnabled'         => $this->gateService->isSlaEnabled(),
            'slaMetrics'         => $this->gateService->isSlaEnabled()
                ? $this->slaService->getMetrics($ticket->site_id)
                : [],
            'attachmentsEnabled' => $this->gateService->canUploadAttachments(),
            'watchers'           => $ticket->watchers ?? [],
            'activities'         => $activities,
            'internalNotes'      => $internalNotes,
            'sidebarTickets'     => $sidebarTickets,
        ]);
    }

    public function poll(Request $request, int $id): JsonResponse
    {
        $ticket = $this->ticketService->getById($id);
        if (! $ticket) {
            abort(404);
        }
        $this->authorize('view', $ticket);

        $lastId = (int) $request->query('last_id', 0);
        $query  = $ticket->replies()->with('user')->orderBy('id');
        if ($lastId > 0) {
            $query->where('id', '>', $lastId);
        }
        $replies = $query->get();

        return response()->json([
            'replies' => $replies->map(fn($r) => [
                'id'          => $r->id,
                'message'     => $r->message,
                'author_type' => $r->author_type,
                'author_name' => $r->user?->name ?? $r->customer_name ?? ($r->author_type === 'staff' ? 'پشتیبان' : 'مشتری'),
                'is_internal' => (bool) ($r->is_internal ?? false),
                'created_at'  => $r->created_at->toIso8601String(),
                'attachments' => $r->attachments ?? [],
            ])->values(),
            'last_id' => $replies->last()?->id ?? $lastId,
        ]);
    }

    public function checkNew(Request $request): JsonResponse
    {
        $lastId  = (int) $request->query('last_id', 0);
        $user    = $request->user();

        $query = Ticket::withoutTenantIsolation()->where('id', '>', $lastId);
        if ($user->is_site_admin && !$user->is_admin) {
            $query->where('site_id', $user->site_id);
        }

        $tickets = $query->orderBy('id')->limit(10)
            ->get(['id', 'ticket_number', 'subject', 'customer_name', 'created_at']);

        // تیکت‌های باز/در جریان که آخرین پاسخ عمومی از customer بوده (نیاز به پاسخ ادمین)
        // نکته: $lastId یک ticket ID است، نه reply ID — مقایسه‌ی قبلی اشتباه بود.
        // این کوئری وضعیت فعلی را بدون نسبت به lastId برمی‌گرداند؛
        // منطق "تازه یا قدیمی" در سمت client مدیریت می‌شود.
        $awaitingReply = \Illuminate\Support\Facades\DB::table('tickets as t')
            ->join('ticket_replies as r', function ($join) {
                $join->on('r.ticket_id', '=', 't.id')
                     ->where('r.is_internal', false)
                     ->whereRaw('r.id = (SELECT MAX(r2.id) FROM ticket_replies r2 WHERE r2.ticket_id = t.id AND r2.is_internal = 0 AND r2.deleted_at IS NULL)');
            })
            ->whereIn('t.status', ['open', 'in_progress'])
            ->whereNull('t.deleted_at')
            ->where('r.author_type', 'customer')
            ->when($user->is_site_admin && !$user->is_admin,
                fn($q) => $q->where('t.site_id', $user->site_id))
            ->count();

        return response()->json([
            'new_count'      => $tickets->count(),
            'awaiting_reply' => $awaitingReply,
            'last_id'        => $tickets->last()?->id ?? $lastId,
            'tickets'        => $tickets->map(fn($t) => [
                'id'            => $t->id,
                'ticket_number' => $t->ticket_number,
                'subject'       => $t->subject,
                'customer_name' => $t->customer_name,
                'url'           => route('tickets.admin.show', $t->id),
            ])->values(),
        ]);
    }

    public function bulkAction(Request $request)
    {
        $this->authorize('viewAny', Ticket::class);

        $action  = $request->input('action');
        $ids     = (array) $request->input('ids', []);
        $ids     = array_filter(array_map('intval', $ids));

        if (empty($ids) || ! in_array($action, ['close', 'reopen', 'delete'], true)) {
            return back()->with('error', 'عملیات یا انتخاب معتبر نیست.');
        }

        $tickets = Ticket::withoutTenantIsolation()->whereIn('id', $ids)->get();
        $count   = 0;

        foreach ($tickets as $ticket) {
            match ($action) {
                'close'  => $this->applyBulkClose($ticket),
                'reopen' => $this->applyBulkReopen($ticket),
                'delete' => $this->applyBulkDelete($ticket),
            };
            $count++;
        }

        $labels = ['close' => 'بسته شدند', 'reopen' => 'بازگشایی شدند', 'delete' => 'حذف شدند'];
        return back()->with('success', "{$count} تیکت {$labels[$action]}.");
    }

    private function applyBulkClose(Ticket $ticket): void
    {
        if ($ticket->status === 'closed') {
            return;
        }
        $old = $ticket->status;
        $ticket->update(['status' => 'closed', 'closed_at' => now()]);
        $this->timeline->recordStatusChanged($ticket->id, $old, 'closed');
    }

    private function applyBulkReopen(Ticket $ticket): void
    {
        if ($ticket->status !== 'closed') {
            return;
        }
        $ticket->update(['status' => 'open', 'closed_at' => null]);
        $this->timeline->recordReopened($ticket->id);
    }

    private function applyBulkDelete(Ticket $ticket): void
    {
        $ticket->delete();
    }

    public function destroy(int $id)
    {
        $ticket = $this->ticketService->getById($id);

        if (!$ticket) {
            abort(404);
        }

        $this->authorize('delete', $ticket);

        $ticket->delete();

        return redirect()
            ->route('tickets.admin.index')
            ->with('success', 'تیکت #' . $ticket->ticket_number . ' با موفقیت حذف شد.');
    }

    protected function getActiveSites()
    {
        return $this->siteRepository->allActive();
    }
}
