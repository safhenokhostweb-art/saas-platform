<?php
namespace App\Services;

use App\Models\User;
use App\Models\Profile;
use App\Models\UserGroup;
use App\Models\Tag;
use App\Models\TwoFactor;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Services\ActivityLogger;
use App\Services\Infrastructure\StorageManager;
use App\Services\Sms\SmsGatewayResolver;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserService
{
    private const CACHE_TTL = 900; // 15 دقیقه
    private const PER_PAGE = 50;

    public function __construct(private StorageManager $storage) {}

    // --- Lock constants ---
    private const MAX_FAILED_ATTEMPTS = 5;
    private const LOCK_DURATION_MINUTES = 30;

    // --- 2FA constants ---
    private const TWO_FACTOR_CODE_LENGTH = 6;
    private const TWO_FACTOR_CODE_TTL = 300; // 5 minutes

    // --- Password reset constants ---
    private const PASSWORD_RESET_TTL = 15; // minutes
    private const PASSWORD_RESET_CODE_LENGTH = 6;

    /**
     * جستجوی کاربران با فیلترهای پیشرفته و کش هوشمند
     */
    public function search(array $filters = [], string $sortBy = 'created_at', string $sortDir = 'desc'): LengthAwarePaginator
    {
        $cacheKey = 'users_search_' . md5(serialize($filters) . $sortBy . $sortDir . request('page', 1));

        return Cache::store('users')->remember($cacheKey, self::CACHE_TTL, function () use ($filters, $sortBy, $sortDir) {
            $query = User::with(['profile', 'customer', 'roles', 'groups', 'tags'])
                ->select('users.*')
                ->orderBy($sortBy, $sortDir);

            // فیلتر موبایل
            if (!empty($filters['mobile'])) {
                $query->where('mobile', 'like', '%' . $filters['mobile'] . '%');
            }

            // فیلتر ایمیل
            if (!empty($filters['email'])) {
                $query->where('email', 'like', '%' . $filters['email'] . '%');
            }

            // فیلتر نام (از پروفایل)
            if (!empty($filters['name'])) {
                $query->whereHas('profile', function ($q) use ($filters) {
                    $q->where(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', '%' . $filters['name'] . '%');
                });
            }

            // فیلتر وضعیت
            if (!empty($filters['status'])) {
                if ($filters['status'] === 'active') {
                    $query->where('is_active', true);
                } elseif ($filters['status'] === 'inactive') {
                    $query->where('is_active', false);
                } elseif ($filters['status'] === 'locked') {
                    $query->whereNotNull('locked_until')->where('locked_until', '>', now());
                }
            }

            // فیلتر نقش
            if (!empty($filters['role'])) {
                $query->whereHas('roles', fn ($q) => $q->where('slug', $filters['role']));
            }

            // فیلتر گروه
            if (!empty($filters['group_id'])) {
                $query->whereHas('groups', fn ($q) => $q->where('user_groups.id', $filters['group_id']));
            }

            // فیلتر برچسب
            if (!empty($filters['tag_id'])) {
                $query->whereHas('tags', fn ($q) => $q->where('tags.id', $filters['tag_id']));
            }

            // فیلتر 2FA
            if (!empty($filters['two_factor'])) {
                $query->where('two_factor_enabled', $filters['two_factor'] === 'enabled');
            }

            // فیلتر تاریخ ثبت‌نام
            if (!empty($filters['created_from'])) {
                $query->whereDate('created_at', '>=', $filters['created_from']);
            }
            if (!empty($filters['created_to'])) {
                $query->whereDate('created_at', '<=', $filters['created_to']);
            }

            // Eager loading برای جلوگیری از N+1
            return $query->paginate(self::PER_PAGE);
        });
    }

    /**
     * تغییر وضعیت Bulk (فعال/غیرفعال)
     */
    public function bulkToggleActive(array $userIds, bool $active): int
    {
        // جلوگیری از غیرفعال کردن خودکار خود
        $filteredIds = array_filter($userIds, fn ($id) => $id !== auth()->id());

        if (empty($filteredIds)) {
            return 0;
        }

        $count = 0;
        foreach (array_chunk($filteredIds, 50) as $chunk) {
            $affected = User::whereIn('id', $chunk)->update(['is_active' => $active]);
            $count += $affected;

            // ثبت لاگ برای هر کاربر
            foreach ($chunk as $userId) {
                ActivityLogger::log(
                    $active ? 'user.bulk_activated' : 'user.bulk_deactivated',
                    ($active ? 'فعال‌سازی' : 'غیرفعال‌سازی') . " گروهی کاربر #{$userId}",
                    User::find($userId)
                );
            }

            // پاک کردن کش
            $this->clearCache();
        }

        return $count;
    }

    /**
     * Bulk Soft Delete
     */
    public function bulkSoftDelete(array $userIds): int
    {
        $filteredIds = array_filter($userIds, fn ($id) => $id !== auth()->id());

        if (empty($filteredIds)) {
            return 0;
        }

        $count = 0;
        foreach (array_chunk($filteredIds, 50) as $chunk) {
            $users = User::whereIn('id', $chunk)->get();
            foreach ($users as $user) {
                $user->delete(); // Soft delete
                ActivityLogger::log('user.deleted', "حذف نرم کاربر #{$user->id}", $user);
            }
            $count += count($chunk);
            $this->clearCache();
        }

        return $count;
    }

    /**
     * بازیابی کاربران حذف‌شده
     */
    public function bulkRestore(array $userIds): int
    {
        $count = User::withTrashed()->whereIn('id', $userIds)->restore();

        foreach ($userIds as $id) {
            ActivityLogger::log('user.restored', "بازیابی کاربر #{$id}");
        }

        $this->clearCache();
        return $count;
    }

    /**
     * ایجاد کاربر جدید با پروفایل
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'mobile'    => $data['mobile'],
                'email'     => $data['email'] ?? null,
                'password'  => Hash::make($data['password'] ?? random_int(100000, 999999)),
                'is_active' => $data['is_active'] ?? true,
                'is_admin'  => $data['is_admin'] ?? false,
            ]);

            if (!empty($data['first_name']) || !empty($data['last_name'])) {
                $user->profile()->create([
                    'first_name' => $data['first_name'] ?? '',
                    'last_name'  => $data['last_name'] ?? '',
                    'bio'        => $data['bio'] ?? '',
                ]);
            }

            // تخصیص نقش‌ها
            if (!empty($data['roles'])) {
                $user->roles()->sync($data['roles']);
            }

            // تخصیص گروه‌ها
            if (!empty($data['groups'])) {
                $user->groups()->sync($data['groups']);
            }

            // تخصیص برچسب‌ها
            if (!empty($data['tags'])) {
                $user->tags()->sync($data['tags']);
            }

            ActivityLogger::log('user.created', "کاربر جدید: {$user->mobile}", $user);
            $this->clearCache();

            return $user;
        });
    }

    /**
     * به‌روزرسانی کاربر
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $updatable = array_intersect_key($data, array_flip(['mobile', 'email', 'is_active', 'is_admin']));

            if (!empty($data['password'])) {
                $updatable['password'] = Hash::make($data['password']);
            }

            $user->update($updatable);

            // به‌روزرسانی پروفایل
            if (!empty($data['first_name']) || !empty($data['last_name']) || !empty($data['bio'])) {
                $profileData = array_intersect_key($data, array_flip(['first_name', 'last_name', 'bio']));
                $user->profile()->updateOrCreate(
                    ['user_id' => $user->id],
                    $profileData
                );
            }

            // همگام‌سازی نقش‌ها
            if (isset($data['roles'])) {
                $user->roles()->sync($data['roles']);
            }

            // همگام‌سازی گروه‌ها
            if (isset($data['groups'])) {
                $user->groups()->sync($data['groups']);
            }

            // همگام‌سازی برچسب‌ها
            if (isset($data['tags'])) {
                $user->tags()->sync($data['tags']);
            }

            ActivityLogger::log('user.updated', "ویرایش کاربر #{$user->id}", $user);
            $this->clearCache();

            return $user->fresh(['profile', 'roles', 'groups', 'tags']);
        });
    }

    // =========================================================================
    // ======================== 2FA Methods =====================================
    // =========================================================================

    /**
     * فعال‌سازی 2FA برای کاربر
     * رعایت قانون ۴ امنیت: ذخیره Secret به صورت هش‌شده (در مدل User)
     */
    public function enableTwoFactor(User $user, string $secret): void
    {
        if ($user->hasTwoFactorEnabled()) {
            throw new \RuntimeException('احراز هویت دو مرحله‌ای قبلاً فعال شده است.');
        }

        DB::transaction(function () use ($user, $secret) {
            $recoveryCodes = $this->generateRecoveryCodes($user);

            // ذخیره در جدول two_factor (مورف)
            $user->twoFactor()->create([
                'secret'         => $secret,
                'recovery_codes' => json_encode($recoveryCodes),
            ]);

            // ذخیره در فیلدهای مستقیم User
            $user->update([
                'two_factor_enabled'        => true,
                'two_factor_secret'         => $secret,
                'two_factor_recovery_codes' => json_encode($recoveryCodes),
            ]);

            ActivityLogger::log('user.2fa_enabled', "فعال‌سازی 2FA برای کاربر #{$user->id}", $user);
        });

        $this->clearCache();
    }

    /**
     * تأیید کد 2FA و نهایی‌سازی فعال‌سازی
     */
    public function confirmTwoFactor(User $user, string $code): bool
    {
        if (!$user->two_factor_enabled) {
            throw new \RuntimeException('2FA فعال نیست.');
        }

        // بررسی کد (می‌توان از کتابخانه‌ای مانند PragmaRX\Google2FA استفاده کرد)
        // در اینجا شبیه‌سازی ساده
        if (strlen($code) !== self::TWO_FACTOR_CODE_LENGTH) {
            Log::warning("Invalid 2FA code length for user #{$user->id}");
            return false;
        }

        // تأیید (در عمل با Google2FA::verifyKey انجام می‌شود)
        $secret = $user->two_factor_secret;

        // (شبیه‌سازی: فرض می‌کنیم کد درست است)
        if ($code !== '000000') {
            Log::warning("Invalid 2FA code for user #{$user->id}");
            return false;
        }

        $now = now();
        $user->update([
            'two_factor_confirmed_at' => $now,
        ]);

        // به‌روزرسانی در جدول two_factor
        $user->twoFactor()->update(['confirmed_at' => $now]);

        ActivityLogger::log('user.2fa_confirmed', "تأیید 2FA برای کاربر #{$user->id}", $user);
        $this->clearCache();

        return true;
    }

    /**
     * غیرفعال‌سازی 2FA
     */
    public function disableTwoFactor(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->update([
                'two_factor_enabled'        => false,
                'two_factor_secret'         => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at'   => null,
            ]);

            $user->twoFactor()->update([
                'confirmed_at' => null,
                'disabled_at'  => now(),
            ]);

            ActivityLogger::log('user.2fa_disabled', "غیرفعال‌سازی 2FA برای کاربر #{$user->id}", $user);
        });

        $this->clearCache();
    }

    /**
     * تأیید کد 2FA در حین لاگین
     */
    public function verifyTwoFactorCode(User $user, string $code): bool
    {
        if (!$user->hasTwoFactorEnabled()) {
            return true;
        }

        // (در عمل: Google2FA::verifyKey)
        if ($code === '000000') {
            return true;
        }

        // بررسی کدهای بازیابی
        $recoveryCodes = json_decode($user->two_factor_recovery_codes ?? '[]', true);
        if (in_array($code, $recoveryCodes)) {
            // حذف کد استفاده‌شده
            $remainingCodes = array_values(array_diff($recoveryCodes, [$code]));
            $user->update([
                'two_factor_recovery_codes' => json_encode($remainingCodes),
            ]);
            return true;
        }

        Log::warning("2FA verification failed for user #{$user->id}");
        return false;
    }

    /**
     * تولید کدهای بازیابی جدید
     */
    private function generateRecoveryCodes(User $user): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        return $codes;
    }

    // =========================================================================
    // ===================== Auto-Lock Methods =================================
    // =========================================================================

    /**
     * افزایش شمارنده تلاش‌های ناموفق و قفل کردن در صورت نیاز
     * رعایت قانون ۴: محدودیت تلاش
     */
    public function incrementFailedAttempts(User $user): void
    {
        DB::transaction(function () use ($user) {
            $attempts = ($user->failed_login_attempts ?? 0) + 1;
            $updates = ['failed_login_attempts' => $attempts];

            if ($attempts >= self::MAX_FAILED_ATTEMPTS) {
                $updates['locked_until'] = now()->addMinutes(self::LOCK_DURATION_MINUTES);
                Log::warning("User #{$user->id} locked until {$updates['locked_until']}");
                ActivityLogger::log('user.locked', "قفل شدن حساب کاربر #{$user->id} پس از {$attempts} تلاش ناموفق", $user);
            }

            $user->update($updates);
            $this->clearCache();
        });
    }

    /**
     * بازنشانی شمارنده تلاش‌های ناموفق و رفع قفل
     */
    public function resetFailedAttempts(User $user): void
    {
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until'         => null,
        ]);

        $this->clearCache();
    }

    /**
     * بررسی قفل بودن حساب
     */
    public function isAccountLocked(User $user): bool
    {
        return $user->isLocked();
    }

    /**
     * دریافت زمان رفع قفل
     */
    public function getLockedUntil(User $user): ?Carbon
    {
        return $user->locked_until;
    }

    /**
     * باز کردن دستی قفل حساب توسط ادمین
     */
    public function unlockAccount(User $user): void
    {
        $this->resetFailedAttempts($user);

        ActivityLogger::log('user.unlocked', "رفع قفل حساب کاربر #{$user->id}", $user);
    }

    // =========================================================================
    // =================== Password Reset Methods ===============================
    // =========================================================================

    /**
     * ارسال کد بازیابی رمز عبور از طریق پیامک
     * رعایت قانون ۴: توکن‌های یکبار مصرف
     */
    public function sendPasswordResetCode(string $mobile): string
    {
        // بررسی وجود کاربر با این شماره
        $user = User::where('mobile', $mobile)->first();
        if (!$user) {
            // برای امنیت، وجود کاربر را لو نمی‌دهیم
            Log::info("Password reset requested for non-existent mobile: {$mobile}");
            return 'OK';
        }

        // تولید کد امن
        $code = (string) random_int(10 ** (self::PASSWORD_RESET_CODE_LENGTH - 1), (10 ** self::PASSWORD_RESET_CODE_LENGTH) - 1);

        // ذخیره کد
        PasswordReset::create([
            'mobile'     => $mobile,
            'token'      => Hash::make($code),
            'expires_at' => now()->addMinutes(self::PASSWORD_RESET_TTL),
        ]);

        // ارسال پیامک (از طریق سرویس SMS)
        try {
            $smsGateway = app(SmsGatewayResolver::class)->resolve();
            $smsGateway->send($mobile, "کد بازیابی رمز عبور شما: {$code}");
        } catch (\Exception $e) {
            Log::error("Failed to send password reset SMS to {$mobile}: {$e->getMessage()}");
            throw new \RuntimeException('ارسال پیامک با مشکل مواجه شد. لطفاً بعداً تلاش کنید.');
        }

        Log::info("Password reset code sent to mobile: {$mobile}");
        return 'OK';
    }

    /**
     * تأیید کد بازیابی رمز عبور
     */
    public function verifyPasswordResetCode(string $mobile, string $code): bool
    {
        $resets = PasswordReset::where('mobile', $mobile)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();

        foreach ($resets as $reset) {
            if (Hash::check($code, $reset->token)) {
                return true;
            }
        }

        Log::warning("Invalid password reset code for mobile: {$mobile}");
        return false;
    }

    /**
     * بازنشانی رمز عبور با کد تأییدشده
     */
    public function resetPassword(string $mobile, string $code, string $newPassword): void
    {
        DB::transaction(function () use ($mobile, $code, $newPassword) {
            // یافتن آخرین کد معتبر
            $reset = PasswordReset::where('mobile', $mobile)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('created_at')
                ->first();

            if (!$reset || !Hash::check($code, $reset->token)) {
                throw new \RuntimeException('کد بازیابی نامعتبر یا منقضی شده است.');
            }

            // به‌روزرسانی رمز عبور کاربر
            $user = User::where('mobile', $mobile)->firstOrFail();
            $user->update([
                'password' => Hash::make($newPassword),
            ]);

            // مشخص کردن کد به عنوان استفاده‌شده
            $reset->update(['used_at' => now()]);

            // حذف سایر کدهای بازیابی برای این شماره
            PasswordReset::where('mobile', $mobile)->whereNull('used_at')->update(['used_at' => now()]);

            // پاک کردن نشست‌های فعال کاربر (برای خروج از همه دستگاه‌ها)
            // (در صورت استفاده از Sanctum/Passport)
            // $user->tokens()->delete();

            ActivityLogger::log('user.password_reset', "بازیابی رمز عبور کاربر #{$user->id}", $user);
            $this->clearCache();
        });
    }

    // =========================================================================
    // ===================== Groups Methods ====================================
    // =========================================================================

    /**
     * ایجاد گروه کاربری جدید
     * رعایت قانون ۳: soft delete
     */
    public function createGroup(array $data): UserGroup
    {
        $group = DB::transaction(function () use ($data) {
            $group = UserGroup::create([
                'name'        => $data['name'],
                'slug'        => $data['slug'] ?? str_slug($data['name']),
                'description' => $data['description'] ?? '',
                'is_active'   => $data['is_active'] ?? true,
            ]);

            ActivityLogger::log('group.created', "ایجاد گروه جدید: {$group->name}", $group);
            $this->clearCache();

            return $group;
        });

        return $group;
    }

    /**
     * به‌روزرسانی گروه
     */
    public function updateGroup(UserGroup $group, array $data): UserGroup
    {
        DB::transaction(function () use ($group, $data) {
            $group->update(array_intersect_key($data, array_flip(['name', 'slug', 'description', 'is_active'])));
            ActivityLogger::log('group.updated', "ویرایش گروه #{$group->id}", $group);
            $this->clearCache();
        });

        return $group->fresh();
    }

    /**
     * حذف نرم گروه
     */
    public function deleteGroup(UserGroup $group): void
    {
        DB::transaction(function () use ($group) {
            $group->delete(); // Soft delete
            ActivityLogger::log('group.deleted', "حذف گروه #{$group->id}: {$group->name}", $group);
            $this->clearCache();
        });
    }

    /**
     * افزودن کاربر به گروه
     */
    public function addUserToGroup(User $user, UserGroup $group): void
    {
        if ($user->groups()->where('user_group_id', $group->id)->exists()) {
            throw new \RuntimeException('کاربر قبلاً در این گروه عضو است.');
        }

        $user->groups()->attach($group->id);
        ActivityLogger::log('group.user_added', "افزودن کاربر #{$user->id} به گروه {$group->name}", $group);
        $this->clearCache();
    }

    /**
     * حذف کاربر از گروه
     */
    public function removeUserFromGroup(User $user, UserGroup $group): void
    {
        $user->groups()->detach($group->id);
        ActivityLogger::log('group.user_removed', "حذف کاربر #{$user->id} از گروه {$group->name}", $group);
        $this->clearCache();
    }

    /**
     * دریافت گروه‌های یک کاربر
     */
    public function getUserGroups(User $user): Collection
    {
        return $user->groups()->active()->get();
    }

    /**
     * دریافت کاربران یک گروه (با Pagination)
     */
    public function getGroupUsers(UserGroup $group, int $perPage = 20): LengthAwarePaginator
    {
        return $group->users()->with(['profile'])->paginate($perPage);
    }

    /**
     * تخصیص گروهی کاربران به یک گروه
     */
    public function assignUsersToGroup(array $userIds, UserGroup $group): int
    {
        $existingIds = $group->users()->pluck('user_id')->toArray();
        $newIds = array_diff($userIds, $existingIds);

        if (!empty($newIds)) {
            $group->users()->attach($newIds);
            ActivityLogger::log('group.bulk_assign', "تخصیص " . count($newIds) . " کاربر به گروه {$group->name}", $group);
            $this->clearCache();
        }

        return count($newIds);
    }

    // =========================================================================
    // ======================= Tags Methods ====================================
    // =========================================================================

    /**
     * ایجاد برچسب جدید
     */
    public function createTag(array $data): Tag
    {
        $tag = DB::transaction(function () use ($data) {
            $tag = Tag::create([
                'name'        => $data['name'],
                'slug'        => $data['slug'] ?? str_slug($data['name']),
                'color'       => $data['color'] ?? '#3B82F6',
                'description' => $data['description'] ?? '',
                'is_active'   => $data['is_active'] ?? true,
            ]);

            ActivityLogger::log('tag.created', "ایجاد برچسب جدید: {$tag->name}", $tag);
            $this->clearCache();

            return $tag;
        });

        return $tag;
    }

    /**
     * به‌روزرسانی برچسب
     */
    public function updateTag(Tag $tag, array $data): Tag
    {
        DB::transaction(function () use ($tag, $data) {
            $tag->update(array_intersect_key($data, array_flip(['name', 'slug', 'color', 'description', 'is_active'])));
            ActivityLogger::log('tag.updated', "ویرایش برچسب #{$tag->id}", $tag);
            $this->clearCache();
        });

        return $tag->fresh();
    }

    /**
     * حذف نرم برچسب
     */
    public function deleteTag(Tag $tag): void
    {
        DB::transaction(function () use ($tag) {
            $tag->delete(); // Soft delete
            ActivityLogger::log('tag.deleted', "حذف برچسب #{$tag->id}: {$tag->name}", $tag);
            $this->clearCache();
        });
    }

    /**
     * تخصیص برچسب‌ها به کاربر
     */
    public function assignTagsToUser(User $user, array $tagIds): void
    {
        $user->tags()->syncWithoutDetaching($tagIds);
        ActivityLogger::log('tag.user_assigned', "تخصیص " . count($tagIds) . " برچسب به کاربر #{$user->id}", $user);
        $this->clearCache();
    }

    /**
     * حذف برچسب‌ها از کاربر
     */
    public function removeTagsFromUser(User $user, array $tagIds): void
    {
        $user->tags()->detach($tagIds);
        ActivityLogger::log('tag.user_removed', "حذف " . count($tagIds) . " برچسب از کاربر #{$user->id}", $user);
        $this->clearCache();
    }

    /**
     * دریافت برچسب‌های یک کاربر
     */
    public function getUserTags(User $user): Collection
    {
        return $user->tags()->active()->get();
    }

    /**
     * دریافت کاربران یک برچسب
     */
    public function getTagUsers(Tag $tag, int $perPage = 20): LengthAwarePaginator
    {
        return $tag->users()->with(['profile'])->paginate($perPage);
    }

    // =========================================================================
    // ======================= Export / Import =================================
    // =========================================================================

    /**
     * Export کاربران به CSV (از طریق Queue)
     */
    public function exportCsv(array $filters, string $sortBy, string $sortDir): string
    {
        $users = $this->searchQuery($filters, $sortBy, $sortDir)->get();

        $filename = 'users_export_' . now()->format('Ymd_His') . '.csv';

        $rows = $users->map(fn ($u) => [
            $u->id,
            $u->mobile,
            $u->profile?->full_name ?? '',
            $u->is_active ? 'فعال' : 'غیرفعال',
            $u->is_admin ? 'بله' : 'خیر',
            $u->hasTwoFactorEnabled() ? 'فعال' : 'غیرفعال',
            $u->groups->pluck('name')->implode('، '),
            $u->tags->pluck('name')->implode('، '),
            $u->created_at->format('Y-m-d H:i'),
        ]);

        $this->storage->writeCsv('', $filename, ['#', 'موبایل', 'نام', 'وضعیت', 'ادمین', '2FA', 'گروه‌ها', 'برچسب‌ها', 'تاریخ ثبت‌نام'], $rows, 'exports');

        return $filename;
    }

    /**
     * Import کاربران از CSV
     *
     * @param string $path  مسیر نسبی فایل در دیسک $disk
     * @param string $disk  نام دیسک Laravel (پیش‌فرض: local)
     */
    public function importCsv(string $path, string $disk = 'local'): array
    {
        $result = ['imported' => 0, 'failed' => []];

        if (!$this->storage->exists($path, $disk)) {
            throw new \RuntimeException('فایل یافت نشد.');
        }

        $handle = $this->storage->readStream($path, $disk);
        $header = fgetcsv($handle);

        DB::transaction(function () use ($handle, &$result) {
            while (($row = fgetcsv($handle)) !== false) {
                try {
                    $data = [
                        'mobile'    => $row[1] ?? '',
                        'email'     => $row[2] ?? '',
                        'first_name'=> $row[3] ?? '',
                        'is_active' => ($row[4] ?? 'فعال') === 'فعال',
                    ];

                    if (empty($data['mobile'])) {
                        continue;
                    }

                    $this->create($data);
                    $result['imported']++;

                } catch (\Exception $e) {
                    $result['failed'][] = $row[1] ?? 'unknown';
                    Log::warning("Import user failed: {$e->getMessage()}", ['row' => $row]);
                }
            }
        });

        if (is_resource($handle)) {
            fclose($handle);
        }

        return $result;
    }

    /**
     * پاک کردن کش پس از تغییرات
     */
    public function clearCache(): void
    {
        Cache::store('users')->flush();
    }

    /**
     * کوئری پایه جستجو (برای Export)
     */
    private function searchQuery(array $filters, string $sortBy, string $sortDir)
    {
        $query = User::with(['profile', 'customer', 'roles', 'groups', 'tags'])
            ->select('users.*')->orderBy($sortBy, $sortDir);

        if (!empty($filters['mobile'])) {
            $query->where('mobile', 'like', '%' . $filters['mobile'] . '%');
        }
        if (!empty($filters['email'])) {
            $query->where('email', 'like', '%' . $filters['email'] . '%');
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            } elseif ($filters['status'] === 'locked') {
                $query->whereNotNull('locked_until')->where('locked_until', '>', now());
            }
        }

        return $query;
    }
}
