<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\MobileRuntime\Assignment\ConversationAssignmentService;

class MobileConversationAssignmentController extends Controller
{
    public function __construct(
        private readonly ConversationAssignmentService $assignmentService,
    ) {}

    // ── POST /api/mobile/conversations/{id}/assign ────────────────────────

    public function assign(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'assignee_id' => 'required|string',
            'reason'      => 'nullable|string|max:500',
        ]);

        $result = $this->assignmentService->assign(
            $id,
            $request->assignee_id,
            (string) $request->user()->id,
            $request->reason ?? ''
        );

        return response()->json(['data' => $result]);
    }

    // ── POST /api/mobile/conversations/{id}/reassign ──────────────────────

    public function reassign(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'assignee_id' => 'required|string',
            'reason'      => 'nullable|string|max:500',
        ]);

        $result = $this->assignmentService->reassign(
            $id,
            $request->assignee_id,
            (string) $request->user()->id,
            $request->reason ?? ''
        );

        return response()->json(['data' => $result]);
    }

    // ── POST /api/mobile/conversations/{id}/unassign ──────────────────────

    public function unassign(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $result = $this->assignmentService->unassign(
            $id,
            (string) $request->user()->id,
            $request->reason ?? ''
        );

        return response()->json(['data' => $result]);
    }

    // ── POST /api/mobile/conversations/{id}/escalate ──────────────────────

    public function escalate(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'target_tier' => 'required|integer|min:1|max:4',
            'reason'      => 'nullable|string|max:500',
        ]);

        try {
            $result = $this->assignmentService->escalate(
                $id,
                (string) $request->user()->id,
                (int) $request->target_tier,
                $request->reason ?? ''
            );
            return response()->json(['data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── GET /api/mobile/conversations/{id}/assignment ─────────────────────

    public function current(string $id): JsonResponse
    {
        $assignment = $this->assignmentService->getCurrentAssignment($id);

        return $assignment
            ? response()->json(['data' => $assignment])
            : response()->json(['data' => null, 'message' => 'Unassigned'], 200);
    }

    // ── GET /api/mobile/conversations/{id}/assignment/history ────────────

    public function history(string $id): JsonResponse
    {
        $history = $this->assignmentService->getAssignmentHistory($id);
        return response()->json(['data' => $history]);
    }
}
