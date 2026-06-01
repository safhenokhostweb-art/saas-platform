<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\MobileRuntime\Inbox\TenantInboxService;
use SaaSPlatform\CoreUtils\MobileRuntime\Inbox\ConversationHubService;

class MobileTenantInboxController extends Controller
{
    public function __construct(
        private readonly TenantInboxService    $inboxService,
        private readonly ConversationHubService $hubService,
    ) {}

    // ── GET /api/mobile/inbox/tenants ──────────────────────────────────────

    public function tenants(Request $request): JsonResponse
    {
        $user     = $request->user();
        $agentId  = (string) $user->id;
        $role     = $user->role ?? 'agent';

        $tenants = $this->inboxService->listAssignedTenants($agentId, $role);

        return response()->json(['data' => $tenants]);
    }

    // ── GET /api/mobile/inbox ──────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $agentId  = (string) $user->id;
        $tenantId = $this->tenantId($request);
        $role     = $user->role ?? 'agent';

        $conversations = $this->inboxService->listAssignedConversations(
            $tenantId,
            $agentId,
            $role,
            (int) ($request->query('limit', 50)),
            $request->query('cursor')
        );

        return response()->json($conversations);
    }

    // ── GET /api/mobile/inbox/summary ─────────────────────────────────────

    public function summary(Request $request): JsonResponse
    {
        $user     = $request->user();
        $agentId  = (string) $user->id;
        $tenantId = $this->tenantId($request);
        $role     = $user->role ?? 'agent';

        $summary = $this->inboxService->getInboxSummary($tenantId, $agentId, $role);

        return response()->json(['data' => $summary]);
    }

    // ── GET /api/mobile/inbox/sla ─────────────────────────────────────────

    public function sla(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $counters = $this->inboxService->getSlaCounters($tenantId);
        return response()->json(['data' => $counters]);
    }

    // ── GET /api/mobile/hub ───────────────────────────────────────────────

    public function hub(Request $request): JsonResponse
    {
        $user     = $request->user();
        $agentId  = (string) $user->id;
        $tenantId = $this->tenantId($request);
        $role     = $user->role ?? 'agent';

        // Platform admins see all tenants; others see their own tenant
        $tenantIds = $role === 'platform_admin'
            ? array_column($this->inboxService->listAssignedTenants($agentId, $role), 'tenant_id')
            : [$tenantId];

        $hub = $this->hubService->getHub($agentId, $role, $tenantIds, [
            'filter'   => $request->query('filter', 'all'),
            'search'   => $request->query('search'),
            'status'   => $request->query('status'),
            'priority' => $request->query('priority'),
            'limit'    => (int) $request->query('limit', 50),
            'cursor'   => $request->query('cursor'),
        ]);

        return response()->json($hub);
    }

    // ── POST /api/mobile/hub/bulk-read ────────────────────────────────────

    public function bulkMarkRead(Request $request): JsonResponse
    {
        $request->validate(['conversation_ids' => 'required|array|max:50']);
        $user    = $request->user();
        $updated = $this->hubService->bulkMarkRead($request->conversation_ids, (string) $user->id);
        return response()->json(['data' => ['updated' => $updated]]);
    }

    // ── GET /api/mobile/dashboard ─────────────────────────────────────────

    public function dashboard(Request $request): JsonResponse
    {
        $user     = $request->user();
        $agentId  = (string) $user->id;
        $tenantId = $this->tenantId($request);
        $role     = $user->role ?? 'agent';

        $summary = $this->inboxService->getInboxSummary($tenantId, $agentId, $role);
        $sla     = $this->inboxService->getSlaCounters($tenantId);

        return response()->json([
            'data' => [
                'open_conversations'     => $summary['open_conversations'],
                'assigned_conversations' => $this->hubService->getHub(
                    $agentId, $role, [$tenantId], ['filter' => 'assigned', 'limit' => 1]
                )['data'] ? count($this->hubService->getHub($agentId, $role, [$tenantId], ['filter' => 'assigned'])['data']) : 0,
                'unresolved_conversations' => $summary['open_conversations'],
                'online_customers'       => $summary['online_users'],
                'sla_warnings'           => $sla['warning'],
                'unread_messages'        => $summary['my_unread'],
                'fetched_at'             => now()->toIso8601String(),
            ],
        ]);
    }

    private function tenantId(Request $request): string
    {
        return (string) (
            $request->attributes->get('tenant_site')?->id
            ?? $request->user()?->site_id
            ?? 'platform'
        );
    }
}
