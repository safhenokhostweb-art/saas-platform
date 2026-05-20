<?php
namespace App\Helpers;

/**
 * پکیج آیکون‌های متنوع برای استفاده در منوها، صفحات و شبکه‌های اجتماعی.
 * همه آیکون‌ها به صورت SVG در ویو admin.pages.partials.icon-svg رندر می‌شوند.
 */
class Icons
{
    /** گروه: شبکه‌های اجتماعی فارسی */
    public const SOCIAL_FA = [
        'telegram' => 'تلگرام',
        'whatsapp' => 'واتس‌اپ',
        'instagram' => 'اینستاگرام',
        'aparat' => 'آپارات',
        'soroush' => 'سروش',
        'eitaa' => 'ایتا',
        'bale' => 'بله',
        'rubika' => 'روبیکا',
    ];

    /** گروه: شبکه‌های اجتماعی خارجی */
    public const SOCIAL_EN = [
        'twitter' => 'توییتر (X)',
        'facebook' => 'فیسبوک',
        'linkedin' => 'لینکدین',
        'youtube' => 'یوتیوب',
        'pinterest' => 'پینترست',
        'tiktok' => 'تیک‌تاک',
        'snapchat' => 'اسنپ‌چت',
        'discord' => 'دیسکورد',
        'github' => 'گیت‌هاب',
    ];

    /** گروه: محتوا و صفحات */
    public const CONTENT = [
        'document' => 'سند',
        'file-text' => 'متن',
        'book' => 'کتاب',
        'info' => 'اطلاعات',
        'help' => 'راهنما',
        'question' => 'سوال',
        'shield' => 'قوانین / امنیت',
        'users' => 'درباره ما / کاربران',
        'home' => 'خانه',
        'link' => 'لینک',
        'globe' => 'سایت / وب',
        'mail' => 'ایمیل',
        'phone' => 'تلفن',
        'envelope' => 'پیام',
    ];

    /** گروه: عملیات و ابزار */
    public const ACTIONS = [
        'search' => 'جستجو',
        'star' => 'مورد علاقه',
        'heart' => 'علاقه',
        'share' => 'اشتراک',
        'download' => 'دانلود',
        'upload' => 'آپلود',
        'settings' => 'تنظیمات',
        'menu' => 'منو',
        'close' => 'بستن',
        'check' => 'تأیید',
        'calendar' => 'تقویم',
        'map-pin' => 'موقعیت',
        'tag' => 'برچسب',
        'folder' => 'پوشه',
        'image' => 'تصویر',
        'video' => 'ویدیو',
        'file' => 'فایل',
        'plus' => 'افزودن',
        'minus' => 'کم کردن',
        'edit' => 'ویرایش',
        'trash' => 'حذف',
        'eye' => 'مشاهده',
        'lock' => 'قفل',
        'unlock' => 'باز کردن قفل',
        'bell' => 'اعلان',
        'user' => 'کاربر',
        'gift' => 'هدیه',
        'award' => 'جایزه',
        'briefcase' => 'کار / خدمات',
    ];

    /** همه آیکون‌ها به صورت name => label (برای استفاده در select) */
    public static function all(): array
    {
        return array_merge(
            self::SOCIAL_FA,
            self::SOCIAL_EN,
            self::CONTENT,
            self::ACTIONS
        );
    }

    /** گروه‌ها برای نمایش در انتخابگر (عنوان گروه => [name => label]) */
    public static function groups(): array
    {
        return [
            'شبکه‌های اجتماعی (فارسی)' => self::SOCIAL_FA,
            'شبکه‌های اجتماعی (خارجی)' => self::SOCIAL_EN,
            'محتوا و صفحات' => self::CONTENT,
            'عملیات و ابزار' => self::ACTIONS,
        ];
    }

    /** آیا نام آیکون معتبر است؟ */
    public static function isValid(string $name): bool
    {
        return array_key_exists($name, self::all());
    }

    /** برچسب آیکون یا نام خودش */
    public static function label(string $name): string
    {
        return self::all()[$name] ?? $name;
    }
}
