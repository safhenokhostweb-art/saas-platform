<?php

namespace SaaSPlatform\CoreUtils\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use SaaSPlatform\CoreUtils\Realtime\GracefulPollingFallback;
use SaaSPlatform\CoreUtils\Analytics\UxInteractionTelemetryService;

class RealtimePollController extends Controller
{
    public function __construct(
        private readonly GracefulPollingFallback        $polling,
        private readonly UxInteractionTelemetryService  $uxTelemetry,
    ) {}

    /** GET /api/realtime/poll */
    public function poll(Request $request): JsonResponse
    {
        $tenantId = (string) ($request->get('tenant_id') ?? session('site_id', ''));
        $since    = $request->get('since');

        $events = $this->polling->pollForTenant($tenantId, $since);

        return response()->json([
            'success' => true,
            'events'  => $events,
            'count'   => count($events),
            'polled_at' => now()->toISOString(),
        ]);
    }

    /** POST /api/ux/rage-click */
    public function recordRageClick(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'element' => 'required|string|max:200',
            'clicks'  => 'required|integer|min:3|max:50',
            'page'    => 'nullable|string|max:500',
        ]);

        $tenantId = (string) session('site_id', '');
        $userId   = (int) (auth()->id() ?? 0);

        $this->uxTelemetry->recordRageClick(
            $tenantId, $userId,
            $validated['element'],
            $validated['clicks'],
            $validated['page'] ?? request()->header('Referer', '')
        );

        return response()->json(['success' => true]);
    }

    /** POST /api/ux/interaction */
    public function recordInteraction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'component' => 'required|string|max:200',
            'action'    => 'required|string|max:100',
            'meta'      => 'nullable|array',
        ]);

        $tenantId = (string) session('site_id', '');
        $userId   = (int) (auth()->id() ?? 0);

        $this->uxTelemetry->recordInteraction(
            $tenantId, $userId,
            $validated['component'],
            $validated['action'],
            $validated['meta'] ?? []
        );

        return response()->json(['success' => true]);
    }

    /** POST /api/ux/performance */
    public function recordPerformance(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'metric' => 'required|string|in:page_load_p95,api_p95,lcp_p75,js_error_rate',
            'value'  => 'required|numeric|min:0',
        ]);

        $this->uxTelemetry->recordPerformanceSample($validated['metric'], (float) $validated['value']);

        return response()->json(['success' => true]);
    }
}
