<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\ConversationService;
use SaaSPlatform\CoreUtils\MobileRuntime\Communication\ConversationParticipant;

class MobileConversationController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user     = $request->user();
        $tenantId = $this->tenantId($request);
        $conversations = $this->conversationService->getConversationsForUser((string) $user->id, $tenantId);
        return response()->json(['data' => $conversations, 'meta' => ['tenant_id' => $tenantId]]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'participants'  => 'required|array|min:1',
            'participants.*' => 'required|string',
            'type'          => 'in:direct,group,support',
            'title'         => 'nullable|string|max:255',
        ]);

        $user     = $request->user();
        $tenantId = $this->tenantId($request);

        $participants = array_unique(array_merge($request->participants, [(string) $user->id]));
        $conversation = $this->conversationService->createConversation(
            $tenantId,
            (string) $user->id,
            $participants,
            $request->type ?? 'direct',
            ['title' => $request->title ?? '']
        );

        return response()->json(['data' => $conversation], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $conversation = \SaaSPlatform\CoreUtils\MobileRuntime\Communication\Conversation::forTenant($tenantId)
            ->with('participants')
            ->find($id);

        if (! $conversation) {
            return response()->json(['message' => 'Conversation not found'], 404);
        }
        return response()->json(['data' => $conversation->toArray()]);
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $result   = $this->conversationService->getMessages($id, $tenantId, 50, $request->query('cursor'));
        return response()->json(['data' => $result['data'], 'meta' => ['next_cursor' => $result['next_cursor'], 'has_more' => $result['has_more']]]);
    }

    public function sendMessage(Request $request, string $id): JsonResponse
    {
        $request->validate(['content' => 'required|string|max:5000', 'type' => 'in:text,image,file']);
        $user    = $request->user();
        $message = $this->conversationService->sendMessage(
            $id,
            (string) $user->id,
            ConversationParticipant::USER_TYPE_END_USER,
            $request->content,
            $request->type ?? 'text',
        );
        return response()->json(['data' => $message], 201);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->validate(['message_id' => 'required|string']);
        $user = $request->user();
        $this->conversationService->markRead($id, (string) $user->id, $request->message_id);
        return response()->json(['data' => ['marked_read' => true]]);
    }

    public function archive(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $this->conversationService->archiveConversation($id, $tenantId);
        return response()->json(['data' => ['archived' => true]]);
    }

    private function tenantId(Request $request): string
    {
        return (string) ($request->attributes->get('tenant_site')?->id ?? $request->user()?->site_id ?? 'platform');
    }
}
