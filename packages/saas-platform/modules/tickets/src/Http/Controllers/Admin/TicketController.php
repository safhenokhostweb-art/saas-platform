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

        $filters = $request->only(['status', 'priority', 'site_id', 'category', 'q', 'sort_by', 'sort_dir', 'per_page']);
        $tickets = $this->ticketService->getAll($filters);
        $stats   = $this->ticketService->getStats();

        return view('tickets::admin.tickets.index', [
            'tickets'    => $tickets,
            'stats'      => $stats,
            'filters'    => $filters,
            'slaEnabled' => $this->gateService->isSlaEnabled(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', \SaaSPlatform\Modules\Tickets\Models\Ticket::class);

        $userModel = config('tickets.user_model', \App\Models\User::class);
        $users     = $userModel::orderBy('mobile')->get(['id', 'mobile']);
        $customers = \App\Models\Customer::orderBy('name')->get(['id', 'name', 'mobile', 'user_id']);
        $sites     = $this->getActiveSites();

        $people = $users->map(fn($u) => [
            'key'         => 'u_' . $u->id,
            'type'        => 'user',
            'user_id'     => $u->id,
            'customer_id' => null,
            'name'        => $u->name ?? $u->mobile ?? '',
            'mobile'      => $u->mobile ?? '',
            'badge'       => 'کاربر پلتفرم',
        ])->merge(
            $customers->map(fn($c) => [
                'key'         => 'c_' . $c->id,
                'type'        => 'customer',
                'user_id'     => $c->user_id,
                'customer_id' => $c->id,
                'name'        => $c->name ?? $c->mobile ?? '',
                'mobile'      => $c->mobile ?? '',
                'badge'       => 'مشتری',
            ])
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

        if (!empty($data['for_customer_id'])) {
            $customer = \App\Models\Customer::find($data['for_customer_id']);
            if ($customer) {
                $data['customer_id']    = $customer->user_id ?? null;
                $data['customer_name']   = $data['customer_name']   ?? $customer->name;
                $data['customer_mobile'] = $data['customer_mobile'] ?? $customer->mobile;
            }
            unset($data['for_customer_id']);
        } elseif (!empty($data['for_user_id'])) {
            $userModel = config('tickets.user_model', \App\Models\User::class);
            $user = $userModel::find($data['for_user_id']);
            if ($user) {
                $data['customer_id']     = $user->id;
                $data['customer_name']   = $data['customer_name']   ?? $user->name;
                $data['customer_mobile'] = $data['customer_mobile'] ?? $user->mobile;
            }
        }
        unset($data['for_user_id'], $data['for_customer_id']);

        $files = $this->gateService->canUploadAttachments()
            ? array_filter((array) $request->file('attachments'))
            : null;
        $files = empty($files) ? null : array_values($files);

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
        $adminUsers = $userModel::where('is_admin', true)->with('profile')->get(['id', 'email', 'mobile']);
        $sites      = $this->siteRepository->allActive();
        $smsEnabled = (bool) Setting::get('tickets.sms_enabled', false);

        $activities    = TicketActivity::where('ticket_id', $ticket->id)
            ->orderBy('created_at', 'asc')
            ->get();
        $internalNotes = $ticket->internalNotes()->get();

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

    protected function getActiveSites()
    {
        return $this->siteRepository->allActive();
    }
}
