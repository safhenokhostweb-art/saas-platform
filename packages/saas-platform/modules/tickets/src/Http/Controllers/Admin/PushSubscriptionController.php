<?php

namespace SaaSPlatform\Modules\Tickets\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use SaaSPlatform\Modules\Tickets\Models\PushSubscription;

/**
 * SUPPORT-SUITE-01 — Section E
 * Store/delete Web Push subscriptions per authenticated admin.
 */
class PushSubscriptionController extends Controller
{
    /**
     * Subscribe this device/browser to push notifications.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint'   => 'required|string|max:500',
            'public_key' => 'nullable|string|max:255',
            'auth_token' => 'nullable|string|max:255',
            'encoding'   => 'nullable|string|in:aesgcm,aes128gcm',
        ]);

        $user = $request->user();

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $request->endpoint)],
            [
                'user_id'       => $user?->id,
                'endpoint'      => $request->endpoint,
                'endpoint_hash' => hash('sha256', $request->endpoint),
                'public_key'    => $request->public_key,
                'auth_token'    => $request->auth_token,
                'encoding'      => $request->encoding ?? 'aesgcm',
                'last_used_at'  => now(),
            ]
        );

        return response()->json(['status' => 'subscribed']);
    }

    /**
     * Remove a push subscription (device unsubscribed or user logged out).
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate(['endpoint' => 'required|string']);

        PushSubscription::where('endpoint_hash', hash('sha256', $request->endpoint))->delete();

        return response()->json(['status' => 'unsubscribed']);
    }

    /**
     * Return the VAPID public key so the browser can subscribe.
     */
    public function vapidKey(): JsonResponse
    {
        return response()->json([
            'vapid_public_key' => config('tickets.push.vapid_public_key', ''),
        ]);
    }
}
