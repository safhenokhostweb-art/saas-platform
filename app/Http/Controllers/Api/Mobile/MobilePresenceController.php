<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\MobileRuntime\Presence\PresenceRuntimeManager;
use SaaSPlatform\CoreUtils\MobileRuntime\Realtime\TypingIndicatorRuntime;

class MobilePresenceController extends Controller
{
    public function __construct(
        private readonly PresenceRuntimeManager $presenceManager,
        private readonly TypingIndicatorRuntime $typingRuntime,
    ) {}

    public function heartbeat(Request $request): JsonResponse
    {
        $request->validate(['device_id' => 'required|string']);
        $user     = $request->user();
        $tenantId = $this->tenantId($request);
        $this->presenceManager->heartbeat($tenantId, (string) $user->id, $request->device_id);
        return response()->json(['data' => ['alive' => true, 'at' => now()->toIso8601String()]]);
    }

    public function getPresence(Request $request, string $userId): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $presence = $this->presenceManager->getPresence($tenantId, $userId);
        return response()->json(['data' => $presence ?? ['user_id' => $userId, 'status' => 'offline']]);
    }

    public function getConversationPresence(Request $request, string $conversationId): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $typing   = $this->typingRuntime->getTypingUsers($conversationId);
        $summary  = $this->presenceManager->getPresenceSummary($tenantId);
        return response()->json([
            'data' => [
                'conversation_id' => $conversationId,
                'typing_users'    => $typing,
                'presence_summary' => $summary,
            ],
        ]);
    }

    public function startTyping(Request $request, string $conversationId): JsonResponse
    {
        $user     = $request->user();
        $tenantId = $this->tenantId($request);
        $this->typingRuntime->startTyping($conversationId, (string) $user->id, $tenantId);
        $this->typingRuntime->broadcast($conversationId, (string) $user->id, true);
        return response()->json(['data' => ['typing' => true]]);
    }

    public function stopTyping(Request $request, string $conversationId): JsonResponse
    {
        $user = $request->user();
        $this->typingRuntime->stopTyping($conversationId, (string) $user->id);
        $this->typingRuntime->broadcast($conversationId, (string) $user->id, false);
        return response()->json(['data' => ['typing' => false]]);
    }

    private function tenantId(Request $request): string
    {
        return (string) ($request->attributes->get('tenant_site')?->id ?? $request->user()?->site_id ?? 'platform');
    }
}
