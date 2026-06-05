<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * SUPPORT-SUITE-01 — Section J
 * Mobile FCM token registration / unregistration.
 * Tokens are stored in push_subscriptions with encoding='fcm' as a distinguishing flag.
 */
class MobilePushController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'token'    => 'required|string|max:500',
            'platform' => 'nullable|string|in:android,ios,windows,linux,macos',
        ]);

        $user = $request->user();

        \SaaSPlatform\Modules\Tickets\Models\PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $request->token)],
            [
                'user_id'       => $user?->id,
                'endpoint'      => $request->token,
                'endpoint_hash' => hash('sha256', $request->token),
                'encoding'      => 'fcm_' . ($request->platform ?? 'android'),
                'last_used_at'  => now(),
            ]
        );

        return response()->json(['status' => 'registered']);
    }

    public function unregister(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        \SaaSPlatform\Modules\Tickets\Models\PushSubscription::where('endpoint_hash', hash('sha256', $request->token))->delete();

        return response()->json(['status' => 'unregistered']);
    }
}
