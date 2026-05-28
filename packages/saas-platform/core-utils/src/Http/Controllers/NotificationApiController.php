<?php

namespace SaaSPlatform\CoreUtils\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use SaaSPlatform\CoreUtils\Notifications\UnifiedNotificationService;
use SaaSPlatform\CoreUtils\Notifications\NotificationPreferenceService;

class NotificationApiController extends Controller
{
    public function __construct(
        private readonly UnifiedNotificationService    $notifications,
        private readonly NotificationPreferenceService $preferences,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = (string) ($request->get('tenant_id') ?? session('site_id', ''));
        $userId   = (int)    ($request->get('user_id') ?? auth()->id() ?? 0);
        $limit    = (int)    ($request->get('limit', 20));

        $unread = $this->notifications->getUnread($tenantId, $userId, $limit);

        return response()->json([
            'success'       => true,
            'notifications' => $unread,
            'unread_count'  => count($unread),
        ]);
    }

    public function markRead(Request $request, string $notificationId): JsonResponse
    {
        $tenantId = (string) ($request->get('tenant_id') ?? session('site_id', ''));
        $userId   = (int)    auth()->id();

        $this->notifications->markRead($tenantId, $userId, $notificationId);

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $tenantId = (string) ($request->get('tenant_id') ?? session('site_id', ''));
        $userId   = (int)    auth()->id();

        $this->notifications->markAllRead($tenantId, $userId);

        return response()->json(['success' => true]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channels'              => 'nullable|array',
            'channels.email'        => 'nullable|boolean',
            'channels.sms'          => 'nullable|boolean',
            'channels.in_app'       => 'nullable|boolean',
            'quiet_hours.enabled'   => 'nullable|boolean',
            'quiet_hours.from'      => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'quiet_hours.to'        => 'nullable|string|regex:/^\d{2}:\d{2}$/',
            'digest_mode'           => 'nullable|string|in:off,hourly,daily',
        ]);

        $tenantId = (string) session('site_id', '');
        $userId   = (int) auth()->id();

        $this->preferences->update($tenantId, $userId, $validated);

        return response()->json(['success' => true]);
    }
}
