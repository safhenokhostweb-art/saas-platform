<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $hasFreeAssignable = Schema::hasColumn('modules', 'free_assignable');
        $hasType           = Schema::hasColumn('modules', 'type');
        $hasVersion        = Schema::hasColumn('modules', 'version');
        $hasPriceIrr       = Schema::hasColumn('modules', 'price_irr');

        $modules = [
            [
                'slug'        => 'installments',
                'title'       => 'خرید قسطی',
                'description' => 'خرید اقساطی با پلن‌های ۳، ۶، ۱۲ و ۲۴ ماهه، پرداخت پیش‌قسط از طریق درگاه، جدول اقساط ماهیانه، پنل کاربر و ادمین.',
                'is_active'   => true,
            ],
            [
                'slug'        => 'translations',
                'title'       => 'چند زبانه هوش مصنوعی',
                'description' => 'ترجمه خودکار محتوا از فارسی به عربی و انگلیسی با Claude Haiku، ذخیره per-tenant، hash-based invalidation.',
                'is_active'   => true,
            ],
            [
                'slug'        => 'homepage_videos',
                'title'       => 'ویدیو صفحه اصلی',
                'description' => 'پخش‌کننده ویدیو حرفه‌ای RTL فارسی، پشتیبانی از YouTube، MP4 و Cloudflare، مدیریت از پنل ادمین.',
                'is_active'   => true,
            ],
            [
                'slug'        => 'access_lists',
                'title'       => 'کنترل دسترسی با لیست',
                'description' => 'کنترل دسترسی به صفحات با لیست کد ملی / نام خانوادگی، ایمپورت اکسل، دروازه تأیید هویت.',
                'is_active'   => true,
            ],
            [
                'slug'        => 'smart_image',
                'title'       => 'آپلود تصویر هوشمند',
                'description' => 'آپلود drag & drop با بهینه‌سازی خودکار WebP، پردازش تصویر با GD، هوک تقویت با LocalAI.',
                'is_active'   => true,
            ],
        ];

        foreach ($modules as $m) {
            if (DB::table('modules')->where('slug', $m['slug'])->exists()) {
                continue;
            }

            $row = array_merge($m, [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($hasType) {
                $row['type'] = 'core';
            }
            if ($hasVersion) {
                $row['version'] = '1.0.0';
            }
            if ($hasPriceIrr) {
                $row['price_irr'] = null;
            }
            if ($hasFreeAssignable) {
                $row['free_assignable'] = true;
            }

            DB::table('modules')->insert($row);
        }
    }

    public function down(): void
    {
        $slugs = ['installments', 'translations', 'homepage_videos', 'access_lists', 'smart_image'];
        DB::table('modules')->whereIn('slug', $slugs)->delete();
    }
};
