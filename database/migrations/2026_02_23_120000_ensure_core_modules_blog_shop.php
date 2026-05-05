<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * اطمینان از وجود ماژول‌های اصلی (وبلاگ، فروشگاه، فرم‌ساز، تبلیغات) در جدول modules.
 * هر کدام به‌صورت ماژول جداگانه در پنل ادمین saas-platform ایجاد و به سایت مشتری اختصاص داده می‌شوند.
 */
return new class extends Migration {
    public function up(): void
    {
        $coreModules = [
            [
                'slug' => 'blog',
                'title' => 'وبلاگ',
                'description' => 'ماژول وبلاگ و مطالب',
                'is_active' => true,
            ],
            [
                'slug' => 'shop',
                'title' => 'فروشگاه',
                'description' => 'ماژول فروشگاه آنلاین',
                'is_active' => true,
            ],
            [
                'slug' => 'forms',
                'title' => 'فرم‌ساز',
                'description' => 'فرم‌های سفارشی برای کاربر نهایی (پروفایل و داشبورد).',
                'is_active' => true,
            ],
            [
                'slug' => 'advertisements',
                'title' => 'تبلیغات',
                'description' => 'بنر، نوشتار رونده، تایپی، مارکی و افکت‌های متنوع برای کاربر نهایی.',
                'is_active' => true,
            ],
        ];

        foreach ($coreModules as $m) {
            $exists = DB::table('modules')->where('slug', $m['slug'])->first();
            if ($exists) {
                DB::table('modules')->where('slug', $m['slug'])->update([
                    'title' => $m['title'],
                    'description' => $m['description'],
                    'is_active' => true,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('modules')->insert(array_merge($m, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    public function down(): void
    {
        DB::table('modules')->whereIn('slug', ['blog', 'shop', 'forms', 'advertisements'])->delete();
    }
};
