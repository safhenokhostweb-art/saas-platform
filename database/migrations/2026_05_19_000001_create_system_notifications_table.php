<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * اعلان‌های سیستمی با قابلیت تفکیک Tenant و وضعیت خوانده‌شدن.
     *
     * تفاوت با platform_site_notifications:
     * - platform_site_notifications → اعلان‌های تبلیغاتی/آموزشی پلتفرم به سایت‌ها (بدون read_at)
     * - system_notifications     → اعلان‌های سیستمی داخلی با قابلیت نشان‌گذاری به عنوان خوانده‌شده
     */
    public function up(): void
    {
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();

            // دریافت‌کننده (پلی‌مورفیک: User, Site, Reseller, …)
            $table->nullableMorphs('notifiable');

            // تفکیک Tenant (برای کوئری‌های سریع بدون JOIN)
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('reseller_id')->nullable()->constrained('resellers')->nullOnDelete();

            // محتوای اعلان
            $table->string('type')->default('info'); // info, success, warning, error
            $table->string('title');
            $table->text('body')->nullable();

            // داده‌های اضافی (لینک، اکشن، metadata)
            $table->json('data')->nullable();

            // وضعیت خوانده‌شدن
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // ایندکس‌های مورد نیاز
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'sys_notif_notifiable_read');
            $table->index(['site_id', 'read_at'], 'sys_notif_site_read');
            $table->index(['reseller_id', 'read_at'], 'sys_notif_reseller_read');
            $table->index('created_at', 'sys_notif_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
    }
};
