<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ActivityLogger
{
    /**
     * ثبت یک رخداد در لاگ فعالیت.
     *
     * @param string $action کلید عملیات (مثلاً auth.login, customer.updated)
     * @param string|null $description توضیح فارسی (اختیاری؛ در صورت ندادن از action برچسب گرفته می‌شود)
     * @param Model|null $subject مدل مرتبط (مشتری، سایت، فرم و...)
     * @param array $properties داده اضافی (مثلاً old/new برای ممیزی)
     */
    public static function log(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        array $properties = []
    ): ?ActivityLog {
        if (!Schema::hasTable('activity_logs')) {
            return null;
        }

        $description = $description ?? ActivityLog::actionLabel($action);
        $request = request();

        $data = [
            'action' => $action,
            'description' => $description,
            'user_id' => Auth::id(),
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ];

        return ActivityLog::create($data);
    }

    public static function login(): void
    {
        self::log('auth.login', 'ورود به سیستم');
    }

    public static function logout(): void
    {
        self::log('auth.logout', 'خروج از سیستم');
    }
}
