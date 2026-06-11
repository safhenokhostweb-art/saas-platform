<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->json('pages')->nullable()->after('footer_blocks');
            $table->string('preview_image')->nullable()->after('thumbnail');
            $table->boolean('is_marketplace')->default(false)->after('status');
            $table->unsignedBigInteger('marketplace_price')->default(0)->after('is_marketplace');
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            $table->dropColumn(['pages', 'preview_image', 'is_marketplace', 'marketplace_price']);
        });
    }
};
