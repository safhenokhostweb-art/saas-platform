<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\MobilePushTokenRegistry;
use SaaSPlatform\CoreUtils\MobileRuntime\Notifications\MobileNotificationPolicyManager;

class MobileNotificationController extends Controller
{
    public function __construct(
        private readonly MobilePushTokenRegistry $pushTokenRegistry,
        private readonly MobileNotificationPolicyManager $policyManager,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Returns notifications from the platform notification system
        $user     = $request->user();
        $notifications = $user?->notifications()->latest()->limit(50)->get() ?? collect();
        return response()->json(['data' => $notifications->toArray(), 'meta' => ['unread_count' => $user?->unreadNotifications()->count() ?? 0]]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $user         = $request->user();
        $notification = $user?->notifications()->find($id);
        $notification?->markAsRead();
        return response()->json(['data' => ['marked_read' => true]]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()?->unreadNotifications->markAsRead();
        return response()->json(['data' => ['marked_all_read' => true]]);
    }

    public function registerPushToken(Request $request): JsonResponse
    {
        $request->validate([
            'token'     => 'required|string',
            'platform'  => 'required|in:fcm,apns',
            'device_id' => 'required|string',
        ]);

        $user     = $request->user();
        $tenantId = $this->tenantId($request);

        $this->pushTokenRegistry->register(
            $request->device_id,
            (string) $user->id,
            $tenantId,
            $request->token,
            $request->platform
        );

        return response()->json(['data' => ['registered' => true]]);
    }

    public function unregisterPushToken(Request $request): JsonResponse
    {
        $request->validate(['device_id' => 'required|string']);
        $this->pushTokenRegistry->unregister($request->device_id);
        return response()->json(['data' => ['unregistered' => true]]);
    }

    public function getPreferences(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $policy   = $this->policyManager->getPolicy($tenantId);
        return response()->json(['data' => $policy]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $this->policyManager->setPolicy($tenantId, $request->only(['quiet_hours', 'allowed_types']));
        return response()->json(['data' => ['updated' => true]]);
    }

    private function tenantId(Request $request): string
    {
        return (string) ($request->attributes->get('tenant_site')?->id ?? $request->user()?->site_id ?? 'platform');
    }
}
