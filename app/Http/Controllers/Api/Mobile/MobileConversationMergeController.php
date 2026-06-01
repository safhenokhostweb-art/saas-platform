<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\MobileRuntime\Merge\ConversationMergeService;
use SaaSPlatform\CoreUtils\MobileRuntime\Merge\ConversationMergePermissionService;

class MobileConversationMergeController extends Controller
{
    public function __construct(
        private readonly ConversationMergeService $mergeService,
        private readonly ConversationMergePermissionService $permissionService,
    ) {}

    // ── POST /api/mobile/conversations/{id}/merge ────────────────────────────
    // ادغام یک یا چند مکالمه به داخل مکالمه هدف

    public function merge(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'source_ids'   => 'required|array|min:1|max:20',
            'source_ids.*' => 'required|string|uuid',
        ]);

        $user     = $request->user();
        $tenantId = $this->tenantId($request);

        if (! $this->permissionService->canMerge($user, $tenantId)) {
            return response()->json(['message' => 'شما دسترسی ادغام مکالمات را ندارید.'], 403);
        }

        try {
            $result = $this->mergeService->merge(
                targetId:     $id,
                sourceIds:    $request->source_ids,
                mergedBy:     (string) $user->id,
                mergedByType: $this->permissionService->resolveUserType($user),
                tenantId:     $tenantId,
            );
            return response()->json(['data' => $result]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    // ── GET /api/mobile/admin/merge-permissions ──────────────────────────────
    // لیست کاربران دارای مجوز ادغام (فقط ادمین)

    public function listPermissions(Request $request): JsonResponse
    {
        $user     = $request->user();
        $tenantId = $this->tenantId($request);

        if (! $this->permissionService->canManagePermissions($user)) {
            return response()->json(['message' => 'دسترسی کافی ندارید.'], 403);
        }

        $grants = $this->permissionService->listGranted($tenantId);
        return response()->json(['data' => $grants]);
    }

    // ── POST /api/mobile/admin/merge-permissions ─────────────────────────────
    // اعطای مجوز ادغام به کاربر (فقط ادمین)

    public function grantPermission(Request $request): JsonResponse
    {
        $request->validate(['user_id' => 'required|string']);

        $user     = $request->user();
        $tenantId = $this->tenantId($request);

        if (! $this->permissionService->canManagePermissions($user)) {
            return response()->json(['message' => 'دسترسی کافی ندارید.'], 403);
        }

        $this->permissionService->grant($tenantId, $request->user_id, (string) $user->id);
        return response()->json(['data' => ['granted' => true, 'user_id' => $request->user_id]]);
    }

    // ── DELETE /api/mobile/admin/merge-permissions/{userId} ─────────────────
    // لغو مجوز ادغام از کاربر (فقط ادمین)

    public function revokePermission(Request $request, string $userId): JsonResponse
    {
        $user     = $request->user();
        $tenantId = $this->tenantId($request);

        if (! $this->permissionService->canManagePermissions($user)) {
            return response()->json(['message' => 'دسترسی کافی ندارید.'], 403);
        }

        $this->permissionService->revoke($tenantId, $userId);
        return response()->json(['data' => ['revoked' => true, 'user_id' => $userId]]);
    }

    // ── GET /api/mobile/admin/merge-permissions/check/{userId} ──────────────
    // بررسی آیا کاربر مجوز ادغام دارد (فقط ادمین)

    public function checkPermission(Request $request, string $userId): JsonResponse
    {
        $user     = $request->user();
        $tenantId = $this->tenantId($request);

        if (! $this->permissionService->canManagePermissions($user)) {
            return response()->json(['message' => 'دسترسی کافی ندارید.'], 403);
        }

        $targetUser = \App\Models\User::find($userId);
        if (! $targetUser) {
            return response()->json(['message' => 'کاربر یافت نشد.'], 404);
        }

        $can = $this->permissionService->canMerge($targetUser, $tenantId);
        return response()->json(['data' => ['user_id' => $userId, 'can_merge' => $can]]);
    }

    // ── GET /api/mobile/conversations/merge/my-permission ───────────────────
    // بررسی دسترسی کاربر جاری

    public function myPermission(Request $request): JsonResponse
    {
        $user     = $request->user();
        $tenantId = $this->tenantId($request);
        $can      = $this->permissionService->canMerge($user, $tenantId);
        return response()->json(['data' => ['can_merge' => $can]]);
    }

    private function tenantId(Request $request): string
    {
        return (string) ($request->attributes->get('tenant_site')?->id ?? $request->user()?->site_id ?? 'platform');
    }
}
