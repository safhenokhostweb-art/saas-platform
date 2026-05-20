<?php
namespace App\Models;

use App\Traits\BelongsToResellerTenant;
use App\Traits\BelongsToSiteTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * مدل اعلان سیستمی — برای اعلان‌های داخلی با قابلیت تفکیک Tenant.
 *
 * ویژگی‌ها:
 * - پلی‌مورفیک (قابل ارسال به User، Site، Reseller و…)
 * - تفکیک Tenant با site_id / reseller_id
 * - قابلیت نشان‌گذاری به عنوان خوانده‌شده (read_at)
 * - داده‌های اضافی به صورت JSON
 *
 * @property int $id
 * @property string|null $notifiable_type
 * @property int|null $notifiable_id
 * @property int|null $site_id
 * @property int|null $reseller_id
 * @property string $type
 * @property string $title
 * @property string|null $body
 * @property array|null $data
 * @property \Illuminate\Support\Carbon|null $read_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * @method static Builder|static unread()
 * @method static Builder|static read()
 * @method static Builder|static forSite(int|Site $site)
 * @method static Builder|static forReseller(int|Reseller $reseller)
 * @method static Builder|static forTenant(string $tenantType, int $tenantId)
 * @method static Builder|static ofType(string $type)
 */
class SystemNotification extends Model
{
    use BelongsToSiteTenant;
    use BelongsToResellerTenant;

    /**
     * نام جدول در دیتابیس.
     */
    protected $table = 'system_notifications';

    /**
     * فیلدهای قابل پر کردن.
     */
    protected $fillable = [
        'notifiable_type',
        'notifiable_id',
        'site_id',
        'reseller_id',
        'type',
        'title',
        'body',
        'data',
        'read_at',
    ];

    /**
     * تبدیل خودکار نوع فیلدها.
     */
    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    // ------------------------------------------------------------------
    // روابط (Relationships)
    // ------------------------------------------------------------------

    /**
     * دریافت‌کننده اعلان (پلی‌مورفیک).
     * می‌تواند User، Site، Reseller یا هر مدل دیگر باشد.
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    // ------------------------------------------------------------------
    // اسکوپ‌های محلی (Local Scopes)
    // ------------------------------------------------------------------

    /**
     * فقط اعلان‌های خوانده‌نشده.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * فقط اعلان‌های خوانده‌شده.
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * تفکیک بر اساس Site.
     */
    public function scopeForSite(Builder $query, Site|int $site): Builder
    {
        $siteId = $site instanceof Site ? $site->id : (int) $site;

        return $query->where('site_id', $siteId);
    }

    /**
     * تفکیک بر اساس Reseller.
     */
    public function scopeForReseller(Builder $query, Reseller|int $reseller): Builder
    {
        $resellerId = $reseller instanceof Reseller ? $reseller->id : (int) $reseller;

        return $query->where('reseller_id', $resellerId);
    }

    /**
     * تفکیک بر اساس Tenant (نوع + شناسه).
     *
     * @param string $tenantType 'site' یا 'reseller'
     */
    public function scopeForTenant(Builder $query, string $tenantType, int $tenantId): Builder
    {
        $column = $tenantType === 'reseller' ? 'reseller_id' : 'site_id';

        return $query->where($column, $tenantId);
    }

    /**
     * فیلتر بر اساس نوع اعلان.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * جدیدترین اعلان‌ها در ابتدا.
     */
    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderByDesc('created_at');
    }

    // ------------------------------------------------------------------
    // متدهای کمکی (Helpers)
    // ------------------------------------------------------------------

    /**
     * آیا این اعلان خوانده شده است؟
     */
    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * آیا این اعلان خوانده نشده است؟
     */
    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    /**
     * نشان‌گذاری به عنوان خوانده‌شده.
     */
    public function markAsRead(): void
    {
        if ($this->isUnread()) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * نشان‌گذاری به عنوان خوانده‌نشده.
     */
    public function markAsUnread(): void
    {
        if ($this->isRead()) {
            $this->update(['read_at' => null]);
        }
    }
}
