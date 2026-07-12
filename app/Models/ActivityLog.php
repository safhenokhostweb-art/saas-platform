<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'action',
        'description',
        'user_id',
        'causer_id',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function causer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    /** مدل مرتبط با subject (مثلاً Customer، Site، Form) */
    public function subject()
    {
        if (!$this->subject_type) {
            return null;
        }
        return $this->subject_type::find($this->subject_id);
    }

    /** برچسب عملیات برای نمایش در لیست */
    public static function actionLabel(string $action): string
    {
        $labels = [
            'auth.login' => 'ورود به سیستم',
            'auth.logout' => 'خروج از سیستم',
            'customer.created' => 'ایجاد مشتری',
            'customer.updated' => 'ویرایش مشتری',
            'customer.deleted' => 'حذف مشتری',
            'site.created' => 'ایجاد سایت',
            'site.updated' => 'ویرایش سایت',
            'site.deleted' => 'حذف سایت',
            'form.created' => 'ایجاد فرم',
            'form.updated' => 'ویرایش فرم',
            'form.deleted' => 'حذف فرم',
            'user.toggled' => 'تغییر وضعیت کاربر',
            'module.activated' => 'فعال‌سازی ماژول پس از پرداخت',
            'module.updated' => 'ویرایش ماژول',
            'module.bulk_updated' => 'بروزرسانی گروهی ماژول‌ها',
            'entitlement_granted'          => 'اعطای entitlement',
            'entitlement_revoked'          => 'لغو entitlement',
            'site_agent.provisioned'       => 'ایجاد ادمین پلتفرم در سایت مشتری',
            'site_agent.revoked'           => 'لغو دسترسی ادمین پلتفرم از سایت مشتری',
            'site_agent.password_viewed'   => 'مشاهده رمز عبور ادمین پلتفرم',
            'site_agent.access_logged'     => 'ثبت دسترسی ادمین پلتفرم به سایت مشتری',
            'installation.credential_viewed' => 'مشاهده رمز عبور ادمین نصب مشتری',
        ];
        return $labels[$action] ?? $action;
    }
}
