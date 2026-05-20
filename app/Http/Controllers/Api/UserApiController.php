<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * API مدیریت امنیت کاربران (2FA, Lock, Password Reset)
 *
 * رعایت قانون ۱: کنترلر فقط درخواست را دریافت، UseCase (UserService) را صدا می‌زند و پاسخ Json برمی‌گرداند.
 * رعایت قانون ۴: اعتبارسنجی کامل ورودی، عدم نمایش جزئیات داخلی خطا.
 * رعایت قانون ۵: خطاهای قابل پیش‌بینی با نوع خطای مناسب.
 * رعایت قانون ۶: خروجی با فرمت یکسان.
 */
class UserApiController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    // =========================================================================
    // ======================= 2FA Endpoints ====================================
    // =========================================================================

    /**
     * POST /api/users/{user}/2fa/enable
     *
     * فعال‌سازی 2FA برای کاربر. کلاینت باید secret معتبر (مثلاً از Google Authenticator) ارسال کند.
     */
    public function enableTwoFactor(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'secret' => 'required|string|size:16', // Base32 encoded secret
        ], [
            'secret.required' => 'فیلد secret الزامی است.',
            'secret.size'     => 'secret باید ۱۶ کاراکتر باشد.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $this->userService->enableTwoFactor($user, $request->input('secret'));

            return response()->json([
                'success' => true,
                'message' => '2FA با موفقیت فعال شد. لطفاً با کد تأیید، فعال‌سازی را نهایی کنید.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * POST /api/users/{user}/2fa/confirm
     *
     * تأیید کد 2FA و نهایی‌سازی فعال‌سازی.
     */
    public function confirmTwoFactor(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ], [
            'code.required' => 'کد تأیید الزامی است.',
            'code.size'     => 'کد تأیید باید ۶ رقم باشد.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $confirmed = $this->userService->confirmTwoFactor($user, $request->input('code'));

        if (!$confirmed) {
            return response()->json([
                'success' => false,
                'message' => 'کد تأیید نامعتبر است.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => '2FA با موفقیت تأیید و فعال شد.',
        ]);
    }

    /**
     * POST /api/users/{user}/2fa/disable
     *
     * غیرفعال‌سازی 2FA.
     */
    public function disableTwoFactor(User $user): JsonResponse
    {
        $this->userService->disableTwoFactor($user);

        return response()->json([
            'success' => true,
            'message' => '2FA با موفقیت غیرفعال شد.',
        ]);
    }

    /**
     * POST /api/users/{user}/2fa/verify
     *
     * تأیید کد 2FA در حین لاگین (برای احراز هویت دومرحله‌ای).
     */
    public function verifyTwoFactor(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ], [
            'code.required' => 'کد تأیید الزامی است.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $valid = $this->userService->verifyTwoFactorCode($user, $request->input('code'));

        if (!$valid) {
            return response()->json([
                'success' => false,
                'message' => 'کد تأیید نامعتبر است.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'کد تأیید معتبر است.',
        ]);
    }

    // =========================================================================
    // ===================== Lock Endpoints ====================================
    // =========================================================================

    /**
     * POST /api/users/{user}/unlock
     *
     * رفع قفل حساب کاربر توسط ادمین. نیار به مجوز admin (با middleware检查组).
     */
    public function unlockAccount(User $user): JsonResponse
    {
        $this->userService->unlockAccount($user);

        return response()->json([
            'success' => true,
            'message' => 'حساب کاربر با موفقیت از قفل خارج شد.',
        ]);
    }

    /**
     * GET /api/users/{user}/lock-status
     *
     * بررسی وضعیت قفل حساب.
     */
    public function lockStatus(User $user): JsonResponse
    {
        $isLocked = $this->userService->isAccountLocked($user);
        $lockedUntil = $isLocked ? $this->userService->getLockedUntil($user) : null;

        return response()->json([
            'success'      => true,
            'is_locked'    => $isLocked,
            'locked_until' => $lockedUntil?->toIso8601String(),
            'failed_attempts' => $user->failed_login_attempts ?? 0,
        ]);
    }

    // =========================================================================
    // =================== Password Reset Endpoints ============================
    // =========================================================================

    /**
     * POST /api/password-reset/send-code
     *
     * ارسال کد بازیابی رمز عبور به شماره موبایل.
     */
    public function sendResetCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|string|regex:/^09[0-9]{9}$/',
        ], [
            'mobile.required' => 'شماره موبایل الزامی است.',
            'mobile.regex'    => 'شماره موبایل نامعتبر است.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $this->userService->sendPasswordResetCode($request->input('mobile'));

            return response()->json([
                'success' => true,
                'message' => 'کد بازیابی به شماره موبایل شما ارسال شد.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/password-reset/verify-code
     *
     * تأیید کد بازیابی رمز عبور.
     */
    public function verifyResetCode(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mobile' => 'required|string|regex:/^09[0-9]{9}$/',
            'code'   => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $valid = $this->userService->verifyPasswordResetCode(
            $request->input('mobile'),
            $request->input('code')
        );

        if (!$valid) {
            return response()->json([
                'success' => false,
                'message' => 'کد بازیابی نامعتبر یا منقضی شده است.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'کد بازیابی معتبر است. می‌توانید رمز عبور جدید را تنظیم کنید.',
        ]);
    }

    /**
     * POST /api/password-reset/reset
     *
     * بازنشانی رمز عبور با کد تأییدشده.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mobile'         => 'required|string|regex:/^09[0-9]{9}$/',
            'code'           => 'required|string|size:6',
            'new_password'   => 'required|string|min:8|confirmed',
            'new_password_confirmation' => 'required|string',
        ], [
            'new_password.min' => 'رمز عبور جدید باید حداقل ۸ کاراکتر باشد.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'خطا در اعتبارسنجی.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $this->userService->resetPassword(
                $request->input('mobile'),
                $request->input('code'),
                $request->input('new_password')
            );

            return response()->json([
                'success' => true,
                'message' => 'رمز عبور با موفقیت بازنشانی شد.',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
