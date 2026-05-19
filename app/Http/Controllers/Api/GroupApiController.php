<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API مدیریت گروه‌های کاربری (CRUD گروه و انتساب کاربران)
 *
 * رعایت قانون ۱: کنترلر فقط واسط، سرویس منطق تجاری را انجام می‌دهد.
 * رعایت قانون ۳: Soft delete اجباری (در سرویس اعمال می‌شود).
 * رعایت قانون ۴: اعتبارسنجی کامل.
 */
class GroupApiController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * GET /api/groups
     *
     * لیست گروه‌ها (به همراه تعداد کاربران).
     */
    public function index(Request $request): JsonResponse
    {
        $groups = UserGroup::withCount('users')
            ->orderBy('name')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $groups,
        ]);
    }

    /**
     * POST /api/groups
     *
     * ایجاد گروه جدید.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255|unique:user_groups,name',
            'slug'        => 'nullable|string|max:255|unique:user_groups,slug',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $group = $this->userService->createGroup($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'گروه با موفقیت ایجاد شد.',
            'data'    => $group,
        ], 201);
    }

    /**
     * GET /api/groups/{group}
     *
     * نمایش جزئیات گروه.
     */
    public function show(UserGroup $group): JsonResponse
    {
        $group->loadCount('users');

        return response()->json([
            'success' => true,
            'data'    => $group,
        ]);
    }

    /**
     * PUT /api/groups/{group}
     *
     * به‌روزرسانی گروه.
     */
    public function update(Request $request, UserGroup $group): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255|unique:user_groups,name,' . $group->id,
            'slug'        => 'nullable|string|max:255|unique:user_groups,slug,' . $group->id,
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $updatedGroup = $this->userService->updateGroup($group, $validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'گروه با موفقیت به‌روزرسانی شد.',
            'data'    => $updatedGroup,
        ]);
    }

    /**
     * DELETE /api/groups/{group}
     *
     * حذف نرم گروه.
     */
    public function destroy(UserGroup $group): JsonResponse
    {
        $this->userService->deleteGroup($group);

        return response()->json([
            'success' => true,
            'message' => 'گروه با موفقیت حذف شد.',
        ]);
    }

    /**
     * POST /api/groups/{group}/users
     *
     * افزودن کاربر به گروه.
     */
    public function addUser(Request $request, UserGroup $group): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $user = User::findOrFail($request->input('user_id'));

        try {
            $this->userService->addUserToGroup($user, $group);

            return response()->json([
                'success' => true,
                'message' => 'کاربر با موفقیت به گروه اضافه شد.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * DELETE /api/groups/{group}/users/{user}
     *
     * حذف کاربر از گروه.
     */
    public function removeUser(UserGroup $group, User $user): JsonResponse
    {
        $this->userService->removeUserFromGroup($user, $group);

        return response()->json([
            'success' => true,
            'message' => 'کاربر با موفقیت از گروه حذف شد.',
        ]);
    }

    /**
     * POST /api/groups/{group}/users/bulk
     *
     * تخصیص گروهی کاربران به گروه.
     */
    public function bulkAssignUsers(Request $request, UserGroup $group): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids'   => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $count = $this->userService->assignUsersToGroup($request->input('user_ids'), $group);

        return response()->json([
            'success' => true,
            'message' => "{$count} کاربر با موفقیت به گروه تخصیص یافتند.",
            'count'   => $count,
        ]);
    }

    /**
     * GET /api/groups/{group}/users
     *
     * دریافت کاربران یک گروه.
     */
    public function users(UserGroup $group, Request $request): JsonResponse
    {
        $usersPage = $this->userService->getGroupUsers($group, $request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $usersPage,
        ]);
    }
}
