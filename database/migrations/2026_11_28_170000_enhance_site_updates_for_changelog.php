<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Make site_id nullable so updates can be fleet-wide (global) or per-site
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE site_updates MODIFY site_id BIGINT UNSIGNED NULL');
        }

        // 2. Add columns for rich changelog & Persian features
        Schema::table('site_updates', function (Blueprint $table) {
            if (!Schema::hasColumn('site_updates', 'title')) {
                $table->string('title')->nullable()->after('id');
            }
            if (!Schema::hasColumn('site_updates', 'version')) {
                $table->string('version')->nullable()->after('title');
            }
            if (!Schema::hasColumn('site_updates', 'shamsi_date')) {
                $table->string('shamsi_date', 32)->nullable()->after('version');
            }
            if (!Schema::hasColumn('site_updates', 'release_date')) {
                $table->date('release_date')->nullable()->after('shamsi_date');
            }
            if (!Schema::hasColumn('site_updates', 'update_type')) {
                $table->string('update_type', 32)->default('feature')->after('release_date');
            }
            if (!Schema::hasColumn('site_updates', 'features')) {
                $table->json('features')->nullable()->after('update_type');
            }
            if (!Schema::hasColumn('site_updates', 'is_published')) {
                $table->boolean('is_published')->default(true)->after('features');
            }
        });

        // 3. Seed/idempotently register current release v1.4.0
        \App\Models\SiteUpdate::updateOrCreate(
            [
                'site_id' => null,
                'version' => 'v1.4.0',
            ],
            [
                'version_label' => 'v1.4.0',
                'title' => 'هدر شناور شیشه‌ای مدرن و پلتفرم Canvas',
                'shamsi_date' => '۱۴۰۵/۰۹/۰۸',
                'release_date' => '2026-11-28',
                'update_type' => 'feature',
                'features' => [
                    [
                        'category' => 'هدر شیشه‌ای مدرن (Glass Nimbus)',
                        'items' => [
                            'پوسته جدید glass_nimbus با افکت شیشه‌ای واقعی و بلور پس‌زمینه (Backdrop Filter)',
                            'پشتیبانی کامل از کشوی منوی موبایل تمام‌صفحه راست‌به‌چپ (RTL)',
                            'اتصال زنده متغیرهای CSS به تنظیمات تب استایل و پیشرفته کانواس'
                        ]
                    ],
                    [
                        'category' => 'پلتفرم Canvas و امنیت دارایی‌ها',
                        'items' => [
                            'کنترل دسترسی تجاری به پوسته‌های پریمیوم و گیت اعتبارسنجی سرور',
                            'امکان لغو اختصاص دارایی‌ها از سایت و پاک‌سازی آنی کش دسترسی',
                            'استقلال کامل تاگل‌های جستجو، سبد خرید، حساب کاربری و حالت شب/روز'
                        ]
                    ],
                    [
                        'category' => 'داشبورد و اعلان‌ها',
                        'items' => [
                            'سیستم یکپارچه لاگ تغییرات و تاریخچه امکانات جدید به فارسی و شمسی',
                            'نمایش اعلان نسخه‌های جدید در بالای داشبورد مشتریان'
                        ]
                    ]
                ],
                'notes' => 'ارائه هدر شناور شیشه‌ای مدرن نئومورفیک/گلس‌مورفیک، اصلاح کشوی موبایل و ارتقای داشبورد ساس و مشتری',
                'distributed_at' => now(),
                'is_published' => true,
            ]
        );
    }

    public function down(): void
    {
        Schema::table('site_updates', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'version',
                'shamsi_date',
                'release_date',
                'update_type',
                'features',
                'is_published',
            ]);
        });
    }
};
