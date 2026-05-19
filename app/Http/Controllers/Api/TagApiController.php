<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * API مدیریت برچسب‌های کاربران (CRUD برچسب و انتساب به کاربران)
 *
 * رعایت قانون ۱: کنترلر فقط واسط.
 * رعایت قانون ۳: Soft delete (در سرویس).
 * رعایت قانون ۴: اعتبارسنجی.
 */
class TagApiController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * GET /api/tags
     *
     * لیست برچسب‌ها.
     */
    public function index(Request $request): JsonResponse
    {
        $tags = Tag::withCount('users')
            ->orderBy('name')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $tags,
        ]);
    }

    /**
     * POST /api/tags
     *
     * ایجاد برچسب جدید.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255|unique:tags,name',
            'slug'        => 'nullable|string|max:255|unique:tags,slug',
            'color'       => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ], [
            'color.regex' => 'رنگ باید به صورت هگزادسیمال معتبر باشد (مثلاً #FF5733).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $tag = $this->userService->createTag($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'برچسب با موفقیت ایجاد شد.',
            'data'    => $tag,
        ], 201);
    }

    /**
     * GET /api/tags/{tag}
     *
     * نمایش جزئیات برچسب.
     */
    public function show(Tag $tag): JsonResponse
    {
        $tag->loadCount('users');

        return response()->json([
            'success' => true,
            'data'    => $tag,
        ]);
    }

    /**
     * PUT /api/tags/{tag}
     *
     * به‌روزرسانی برچسب.
     */
    public function update(Request $request, Tag $tag): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:255|unique:tags,name,' . $tag->id,
            'slug'        => 'nullable|string|max:255|unique:tags,slug,' . $tag->id,
            'color'       => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
        ], [
            'color.regex' => 'رنگ باید به صورت هگزادسیمال معتبر باشد (مثلاً #FF5733).',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $updatedTag = $this->userService->updateTag($tag, $validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'برچسب با موفقیت به‌روزرسانی شد.',
            'data'    => $updatedTag,
        ]);
    }

    /**
     * DELETE /api/tags/{tag}
     *
     * حذف نرم برچسب.
     */
    public function destroy(Tag $tag): JsonResponse
    {
        $this->userService->deleteTag($tag);

        return response()->json([
            'success' => true,
            'message' => 'برچسب با موفقیت حذف شد.',
        ]);
    }

    /**
     * POST /api/users/{user}/tags
     *
     * تخصیص برچسب‌ها به کاربر.
     */
    public function assignTags(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tag_ids'   => 'required|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $this->userService->assignTagsToUser($user, $request->input('tag_ids'));

        return response()->json([
            'success' => true,
            'message' => 'برچسب‌ها با موفقیت به کاربر تخصیص یافتند.',
        ]);
    }

    /**
     * DELETE /api/users/{user}/tags
     *
     * حذف برچسب‌ها از کاربر.
     */
    public function removeTags(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tag_ids'   => 'required|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $this->userService->removeTagsFromUser($user, $request->input('tag_ids'));

        return response()->json([
            'success' => true,
            'message' => 'برچسب‌ها با موفقیت از کاربر حذف شدند.',
        ]);
    }

    /**
     * GET /api/users/{user}/tags
     *
     * دریافت برچسب‌های یک کاربر.
     */
    public function userTags(User $user): JsonResponse
    {
        $tags = $this->userService->getUserTags($user);

        return response()->json([
            'success' => true,
            'data'    => $tags,
        ]);
    }

    /**
     * GET /api/tags/{tag}/users
     *
     * دریافت کاربران یک برچسب.
     */
    public function tagUsers(Tag $tag, Request $request): JsonResponse
    {
        $usersPage = $this->userService->getTagUsers($tag, $request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data'    => $usersPage,
        ]);
    }
}
