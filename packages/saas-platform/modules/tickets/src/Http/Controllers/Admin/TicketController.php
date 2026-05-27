<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteRepositoryInterface;
use SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface;
use SaaSPlatform\Modules\Tickets\Services\TicketGateService;
use SaaSPlatform\Modules\Tickets\Services\TicketSlaService;
use SaaSPlatform\Modules\Tickets\Services\TicketNotificationService;
use SaaSPlatform\Modules\Tickets\Http\Requests\Admin\StoreTicketRequest;
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
        $users     = $userModel::where('is_admin', false)->orderBy('name')->get(['id', 'name', 'email']);
        $sites     = $this->getActiveSites();

        return view('tickets::admin.tickets.create', [
            'users'               => $users,
            'sites'               => $sites,
            'attachmentsEnabled'  => $this->gateService->canUploadAttachments(),
            'autoAssignEnabled'   => $this->gateService->isAutoAssignEnabled(),
        ]);
    }

    public function store(StoreTicketRequest $request)
    {
        $this->authorize('create', \SaaSPlatform\Modules\Tickets\Models\Ticket::class);

        $data = $request->validated();

        if (!empty($data['for_user_id'])) {
            $userModel = config('tickets.user_model', \App\Models\User::class);
            $user = $userModel::find($data['for_user_id']);
            if ($user) {
                $data['customer_id']    = $user->id;
                $data['customer_name']  = $data['customer_name']  ?? $user->name;
                $data['customer_email'] = $data['customer_email'] ?? $user->email;
            }
            unset($data['for_user_id']);
        }

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
        $adminUsers = $userModel::where('is_admin', true)->get(['id', 'name']);
        $sites      = $this->siteRepository->allActive();
        $smsEnabled = (bool) Setting::get('tickets.sms_enabled', false);

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
        ]);
    }

    protected function getActiveSites()
    {
        return $this->siteRepository->allActive();
    }
}
