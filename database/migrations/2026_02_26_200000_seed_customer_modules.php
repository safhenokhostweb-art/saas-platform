<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $modules = [
            [
                'slug' => 'coupon',
                'title' => 'کوپن تخفیف',
                'description' => 'کوپن‌های تخفیف با کد، درصد تخفیف، تاریخ انقضا و محدودیت استفاده.',
                'is_active' => true,
            ],
            [
                'slug' => 'reviews',
                'title' => 'نظرات و امتیازدهی',
                'description' => 'امتیازدهی و نظرات برای محصولات و مطالب.',
                'is_active' => true,
            ],
            [
                'slug' => 'referral',
                'title' => 'معرفی دوستان',
                'description' => 'کدهای معرفی و ردیابی معرفی‌ها.',
                'is_active' => true,
            ],
            [
                'slug' => 'subscription',
                'title' => 'اشتراک',
                'description' => 'پلن‌های عضویت و مدیریت اشتراک.',
                'is_active' => true,
            ],
            [
                'slug' => 'whatsapp',
                'title' => 'پاسخگویی شبکه‌ها',
                'description' => 'ویجت دکمه/لینک تماس واتساپ، تلگرام و سایر شبکه‌های اجتماعی.',
                'is_active' => true,
            ],
            [
                'slug' => 'sms',
                'title' => 'پیامک',
                'description' => 'ارسال پیامک و یکپارچه‌سازی سرویس پیامک.',
                'is_active' => true,
            ],
            [
                'slug' => 'courses',
                'title' => 'دوره‌ها',
                'description' => 'دوره‌ها، کلاس‌ها و ثبت‌نام.',
                'is_active' => true,
            ],
            [
                'slug' => 'invoice',
                'title' => 'فاکتور آنلاین',
                'description' => 'فاکتور آنلاین با لینک پرداخت.',
                'is_active' => true,
            ],
            [
                'slug' => 'business_card',
                'title' => 'کارت ویزیت دیجیتال',
                'description' => 'صفحه کارت ویزیت دیجیتال.',
                'is_active' => true,
            ],
            [
                'slug' => 'social_links',
                'title' => 'لینک‌های شبکه‌های اجتماعی',
                'description' => 'لینک‌های شبکه‌های اجتماعی (اینستاگرام، توییتر، تلگرام، لینکدین، یوتیوب، روبیکا، بله، ایتا، سروش، گپ).',
                'is_active' => true,
            ],
            [
                'slug' => 'notifications',
                'title' => 'اعلان‌ها',
                'description' => 'اعلان‌های داشبورد ادمین سایت مشتری.',
                'is_active' => true,
            ],
            [
                'slug' => 'advertisements',
                'title' => 'تبلیغات',
                'description' => 'بنر، نوشتار رونده، تایپی، مارکی و افکت‌های متنوع برای کاربر نهایی.',
                'is_active' => true,
            ],
            [
                'slug' => 'forms',
                'title' => 'فرم‌ساز',
                'description' => 'فرم‌های سفارشی برای کاربر نهایی (پروفایل و داشبورد).',
                'is_active' => true,
            ],
        ];

        foreach ($modules as $m) {
            if (DB::table('modules')->where('slug', $m['slug'])->exists()) {
                continue;
            }
            DB::table('modules')->insert(array_merge($m, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        $slugs = ['coupon', 'reviews', 'referral', 'subscription', 'whatsapp', 'sms', 'courses', 'invoice', 'business_card', 'social_links', 'notifications', 'advertisements', 'forms'];
        DB::table('modules')->whereIn('slug', $slugs)->delete();
    }
};
