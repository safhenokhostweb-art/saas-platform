<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\MobileRuntime\Realtime\MobileRealtimeCoordinator;
use SaaSPlatform\CoreUtils\MobileRuntime\Realtime\RuntimeSocketAuthorizationService;
use SaaSPlatform\CoreUtils\MobileRuntime\Telemetry\MobileRealtimeDiagnostics;

class MobileRealtimeController extends Controller
{
    public function __construct(
        private readonly MobileRealtimeCoordinator $coordinator,
        private readonly RuntimeSocketAuthorizationService $authService,
        private readonly MobileRealtimeDiagnostics $diagnostics,
    ) {}

    public function getConnectionConfig(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $config   = $this->coordinator->getConnectionConfig($tenantId);
        return response()->json(['data' => $config]);
    }

    public function authorizeChannel(Request $request): JsonResponse
    {
        $request->validate([
            'channel_name' => 'required|string',
            'socket_id'    => 'required|string',
        ]);

        $user     = $request->user();
        $tenantId = $this->tenantId($request);

        $authorized = $this->authService->authorizeChannel(
            $request->channel_name,
            (string) $user->id,
            $tenantId
        );

        if (! $authorized) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Return Pusher-compatible auth response
        $token    = $this->authService->generateAuthToken($request->channel_name, (string) $user->id, $tenantId);
        $authData = ['auth' => config('broadcasting.connections.reverb.key') . ':' . $token];

        // For presence channels add channel_data
        if (str_starts_with($request->channel_name, 'presence-')) {
            $authData['channel_data'] = json_encode([
                'user_id'   => $user->id,
                'user_info' => ['name' => $user->name, 'tenant_id' => $tenantId],
            ]);
        }

        return response()->json($authData);
    }

    public function getStats(Request $request): JsonResponse
    {
        $tenantId    = $this->tenantId($request);
        $stats       = $this->coordinator->getRealtimeStats($tenantId);
        $diagnostics = $this->diagnostics->getRealtimeHealth();
        return response()->json([
            'data' => array_merge($stats, ['diagnostics' => $diagnostics]),
        ]);
    }

    private function tenantId(Request $request): string
    {
        return (string) ($request->attributes->get('tenant_site')?->id ?? $request->user()?->site_id ?? 'platform');
    }
}
