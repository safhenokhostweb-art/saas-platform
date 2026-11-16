<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-087: the site's business vertical (SaaSPlatform\ThemeRuntime\Access\
 * BusinessVerticals), the condition a vertical-exclusive asset kit checks.
 * Null for every existing site: no vertical, so no vertical-exclusive kit
 * reaches it until the platform owner sets one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sites') || Schema::hasColumn('sites', 'business_vertical')) {
            return;
        }
        Schema::table('sites', function (Blueprint $table) {
            $table->string('business_vertical', 64)->nullable()->index();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('sites') && Schema::hasColumn('sites', 'business_vertical')) {
            Schema::table('sites', function (Blueprint $table) {
                $table->dropIndex(['business_vertical']);
                $table->dropColumn('business_vertical');
            });
        }
    }
};
