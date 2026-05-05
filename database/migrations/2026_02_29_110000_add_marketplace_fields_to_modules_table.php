<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فیلدهای مارکت‌پلیس برای نمایش ماژول در فروشگاه tenant.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->boolean('marketplace_visible')->default(true)->after('is_active');
            $table->string('category', 64)->nullable()->after('marketplace_visible');
            $table->string('license_type_default', 32)->default('one_time')->after('price_irr'); // one_time | subscription
            $table->text('short_description')->nullable()->after('description');
            $table->json('screenshots')->nullable()->after('short_description'); // ['url1', 'url2']
            $table->string('currency', 8)->default('IRR')->after('price_irr');
            $table->unsignedBigInteger('price_promo_irr')->nullable()->after('price_irr');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn([
                'marketplace_visible',
                'category',
                'license_type_default',
                'short_description',
                'screenshots',
                'currency',
                'price_promo_irr',
            ]);
        });
    }
};
