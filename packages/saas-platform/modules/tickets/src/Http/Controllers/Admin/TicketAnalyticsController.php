<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use SaaSPlatform\CoreUtils\Analytics\OperationalDashboardService;

class TicketAnalyticsController
{
    public function __construct(
        private readonly OperationalDashboardService $dashboard
    ) {}

    public function index(Request $request): View
    {
        $tenantId = $request->get('site_id') ?? session('active_site_id', 'default');

        return view('tickets::admin.tickets.analytics', [
            'realtime' => $this->dashboard->getRealtimeOperations($tenantId),
            'support'  => $this->dashboard->getSupportAnalytics($tenantId),
            'forms'    => $this->dashboard->getFormAnalytics($tenantId),
            'health'   => $this->dashboard->getTenantHealth($tenantId),
            'queue'    => $this->dashboard->getQueueHealth(),
            'security' => $this->dashboard->getSecurityPosture(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $tenantId = $request->get('site_id') ?? session('active_site_id', 'default');
        $period   = $request->get('period', '7d');

        return response()->json([
            'realtime' => $this->dashboard->getRealtimeOperations($tenantId),
            'support'  => $this->dashboard->getSupportAnalytics($tenantId, $period),
            'forms'    => $this->dashboard->getFormAnalytics($tenantId, $period),
            'health'   => $this->dashboard->getTenantHealth($tenantId),
            'queue'    => $this->dashboard->getQueueHealth(),
            'security' => $this->dashboard->getSecurityPosture(),
        ]);
    }
}
