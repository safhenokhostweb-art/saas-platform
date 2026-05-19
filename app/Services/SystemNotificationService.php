<?php

namespace App\Services;

use App\Models\Reseller;
use App\Models\Site;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * سرویس مدیریت اعلان‌های سیستمی.
 *
 * وظایف:
 * - ذخیره اعلان‌ها در دیتابیس
 * - تفکیک اعلان‌ها بر اساس Tenant (Site / Reseller)
 * - شمارش اعلان‌های خوانده‌نشده
 * - نشان‌گذاری به عنوان خوانده‌شده
 *
 * نحوه استفاده:
 * ```php
 * // ارسال اعلان به یک کاربر
 * SystemNotificationService::notify($user, 'title', 'body', 'info', ['url' => '/admin']);
 *
 * // ارسال اعلان به یک سایت (مربوط به tenant)
 * SystemNotificationService::notifySite($site, 'title', 'body', 'warning');
 *
 * // شمارش اعلان‌های خوانده‌نشده برای کاربر فعلی
 * $count = SystemNotificationService::unreadCount();
 * ```
 */
class SystemNotificationService
{
    // ------------------------------------------------------------------
    // ثابت‌های نوع اعلان
    // ------------------------------------------------------------------

    public const TYPE_INFO    = 'info';
    public const TYPE_SUCCESS = 'success';
    public const TYPE_WARNING = 'warning';
    public const TYPE_ERROR   = 'error';

    // ------------------------------------------------------------------
    // ایجاد اعلان (Create / Store)
    // ------------------------------------------------------------------

    /**
     * یک اعلان سیستمی جدید ایجاد کن.
     *
     * @param Model|null        $notifiable  دریافت‌کننده (User, Site, Reseller, …)
     * @param string            $title       عنوان اعلان
     * @param string|null       $body        متن اعلان
     * @param string            $type        نوع (info, success, warning, error)
     * @param array|null        $data        داده‌های اضافی (لینک، کلیدهای عملیاتی و…)
     * @param Site|int|null     $site        Site (برای تفکیک Tenant در صورت نیاز)
     * @param Reseller|int|null $reseller    Reseller (برای تفکیک Tenant در صورت نیاز)
     *
     * @return SystemNotification|null
     */
    public static function store(
        string $title,
        ?string $body = null,
        string $type = self::TYPE_INFO,
        ?array $data = null,
        ?Model $notifiable = null,
        Site|int|null $site = null,
        Reseller|int|null $reseller = null,
    ): ?SystemNotification {
        if (! self::tableReady()) {
            return null;
        }

        $payload = [
            'title' => $title,
            'body'  => $body,
            'type'  => $type,
            'data'  => $data,
        ];

        // مقداردهی notifiable
        if ($notifiable !== null) {
            $payload['notifiable_type'] = get_class($notifiable);
            $payload['notifiable_id']   = $notifiable->getKey();
        }

        // مقداردهی site_id
        if ($site !== null) {
            $payload['site_id'] = $site instanceof Site ? $site->id : (int) $site;
        } elseif ($notifiable instanceof Site) {
            $payload['site_id'] = $notifiable->id;
        } elseif ($notifiable instanceof User && $notifiable->relationLoaded('site') && $notifiable->site) {
            $payload['site_id'] = $notifiable->site->id;
        }

        // مقداردهی reseller_id
        if ($reseller !== null) {
            $payload['reseller_id'] = $reseller instanceof Reseller ? $reseller->id : (int) $reseller;
        } elseif ($notifiable instanceof Reseller) {
            $payload['reseller_id'] = $notifiable->id;
        }

        return SystemNotification::create($payload);
    }

    /**
     * یک اعلان مستقیم به یک Site ارسال کن.
     */
    public static function notifySite(
        Site|int $site,
        string $title,
        ?string $body = null,
        string $type = self::TYPE_INFO,
        ?array $data = null,
    ): ?SystemNotification {
        $siteModel = $site instanceof Site ? $site : Site::find($site);
        if (! $siteModel) {
            return null;
        }

        return self::store(
            notifiable: $siteModel,
            title: $title,
            body: $body,
            type: $type,
            data: $data,
            site: $siteModel,
        );
    }

    /**
     * یک اعلان مستقیم به یک Reseller ارسال کن.
     */
    public static function notifyReseller(
        Reseller|int $reseller,
        string $title,
        ?string $body = null,
        string $type = self::TYPE_INFO,
        ?array $data = null,
    ): ?SystemNotification {
        $resellerModel = $reseller instanceof Reseller ? $reseller : Reseller::find($reseller);
        if (! $resellerModel) {
            return null;
        }

        return self::store(
            notifiable: $resellerModel,
            title: $title,
            body: $body,
            type: $type,
            data: $data,
            reseller: $resellerModel,
        );
    }

    /**
     * یک اعلان مستقیم به یک User ارسال کن.
     */
    public static function notifyUser(
        User|int $user,
        string $title,
        ?string $body = null,
        string $type = self::TYPE_INFO,
        ?array $data = null,
    ): ?SystemNotification {
        $userModel = $user instanceof User ? $user : User::find($user);
        if (! $userModel) {
            return null;
        }

        return self::store(
            notifiable: $userModel,
            title: $title,
            body: $body,
            type: $type,
            data: $data,
        );
    }

    // ------------------------------------------------------------------
    // خواندن (Read) / دریافت
    // ------------------------------------------------------------------

    /**
     * آخرین اعلان‌ها را برای یک دریافت‌کننده برگردان.
     *
     * @param Model $notifiable
     * @param int   $limit
     * @param bool  $unreadOnly  فقط اعلان‌های خوانده‌نشده
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SystemNotification>
     */
    public static function latestForNotifiable(
        Model $notifiable,
        int $limit = 20,
        bool $unreadOnly = false,
    ) {
        $query = SystemNotification::query()
            ->where('notifiable_type', get_class($notifiable))
            ->where('notifiable_id', $notifiable->getKey())
            ->latest();

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->limit($limit)->get();
    }

    /**
     * جدیدترین اعلان‌ها را برای یک Tenant خاص برگردان.
     *
     * @param string $tenantType 'site' یا 'reseller'
     * @param int    $tenantId
     * @param int    $limit
     * @param bool   $unreadOnly
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SystemNotification>
     */
    public static function latestForTenant(
        string $tenantType,
        int $tenantId,
        int $limit = 20,
        bool $unreadOnly = false,
    ) {
        $query = SystemNotification::query()
            ->forTenant($tenantType, $tenantId)
            ->latest();

        if ($unreadOnly) {
            $query->unread();
        }

        return $query->limit($limit)->get();
    }

    /**
     * جدیدترین اعلان‌ها را برای Tenant فعلی (با استفاده از TenantContext) برگردان.
     *
     * @param int  $limit
     * @param bool $unreadOnly
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SystemNotification>
     */
    public static function latestForCurrentTenant(int $limit = 20, bool $unreadOnly = false)
    {
        $tenantContext = app(TenantContext::class);
        $tenant = $tenantContext->current();

        if (! $tenant) {
            return collect();
        }

        $tenantType = $tenantContext->type();
        if (! $tenantType || ! isset($tenant->id)) {
            return collect();
        }

        return self::latestForTenant($tenantType, $tenant->id, $limit, $unreadOnly);
    }

    // ------------------------------------------------------------------
    // شمارش خوانده‌نشده (Unread Count)
    // ------------------------------------------------------------------

    /**
     * تعداد اعلان‌های خوانده‌نشده را برای یک دریافت‌کننده برگردان.
     */
    public static function unreadCountForNotifiable(?Model $notifiable = null): int
    {
        if (! $notifiable || ! self::tableReady()) {
            return 0;
        }

        return SystemNotification::query()
            ->where('notifiable_type', get_class($notifiable))
            ->where('notifiable_id', $notifiable->getKey())
            ->unread()
            ->count();
    }

    /**
     * تعداد اعلان‌های خوانده‌نشده را برای Tenant فعلی برگردان.
     */
    public static function unreadCountForCurrentTenant(): int
    {
        if (! self::tableReady()) {
            return 0;
        }

        $tenantContext = app(TenantContext::class);
        $tenant = $tenantContext->current();

        if (! $tenant) {
            return 0;
        }

        $tenantType = $tenantContext->type();
        if (! $tenantType || ! isset($tenant->id)) {
            return 0;
        }

        return SystemNotification::query()
            ->forTenant($tenantType, $tenant->id)
            ->unread()
            ->count();
    }

    /**
     * تعداد کل اعلان‌های خوانده‌نشده (بدون تفکیک Tenant).
     * مناسب برای ادمین پلتفرم.
     */
    public static function globalUnreadCount(): int
    {
        if (! self::tableReady()) {
            return 0;
        }

        return SystemNotification::query()->unread()->count();
    }

    // ------------------------------------------------------------------
    // نشان‌گذاری (Mark)
    // ------------------------------------------------------------------

    /**
     * یک اعلان مشخص را به عنوان خوانده‌شده نشان‌گذاری کن.
     *
     * @param int|SystemNotification $notification
     *
     * @return bool
     */
    public static function markAsRead(int|SystemNotification $notification): bool
    {
        $model = $notification instanceof SystemNotification
            ? $notification
            : SystemNotification::find($notification);

        if (! $model) {
            return false;
        }

        $model->markAsRead();

        return true;
    }

    /**
     * همهٔ اعلان‌های یک دریافت‌کننده را به عنوان خوانده‌شده نشان‌گذاری کن.
     *
     * @return int تعداد اعلان‌های به‌روز شده
     */
    public static function markAllAsReadForNotifiable(Model $notifiable): int
    {
        if (! self::tableReady()) {
            return 0;
        }

        return SystemNotification::query()
            ->where('notifiable_type', get_class($notifiable))
            ->where('notifiable_id', $notifiable->getKey())
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * همهٔ اعلان‌های خوانده‌نشده یک Tenant را به عنوان خوانده‌شده نشان‌گذاری کن.
     *
     * @return int تعداد اعلان‌های به‌روز شده
     */
    public static function markAllAsReadForTenant(string $tenantType, int $tenantId): int
    {
        if (! self::tableReady()) {
            return 0;
        }

        return SystemNotification::query()
            ->forTenant($tenantType, $tenantId)
            ->unread()
            ->update(['read_at' => now()]);
    }

    // ------------------------------------------------------------------
    // حذف (Delete)
    // ------------------------------------------------------------------

    /**
     * یک اعلان را حذف کن.
     */
    public static function deleteNotification(int|SystemNotification $notification): bool
    {
        $model = $notification instanceof SystemNotification
            ? $notification
            : SystemNotification::find($notification);

        if (! $model) {
            return false;
        }

        return (bool) $model->delete();
    }

    /**
     * همهٔ اعلان‌های قدیمی (مثلاً بیشتر از ۳۰ روز) را حذف کن.
     * مناسب برای Cron job.
     *
     * @param int $olderThanDays
     *
     * @return int تعداد حذف شده
     */
    public static function pruneOldNotifications(int $olderThanDays = 30): int
    {
        if (! self::tableReady()) {
            return 0;
        }

        return SystemNotification::query()
            ->where('created_at', '<', now()->subDays($olderThanDays))
            ->delete();
    }

    // ------------------------------------------------------------------
    // متدهای کمکی داخلی
    // ------------------------------------------------------------------

    /**
     * آیا جدول system_notifications وجود دارد؟
     */
    protected static function tableReady(): bool
    {
        return Schema::hasTable('system_notifications');
    }
}
