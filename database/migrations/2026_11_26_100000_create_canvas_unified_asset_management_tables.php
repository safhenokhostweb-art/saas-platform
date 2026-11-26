<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Button Packs
        if (!Schema::hasTable('canvas_button_packs')) {
            Schema::create('canvas_button_packs', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 120)->unique();
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->string('category_label', 120)->default('مدرن');
                $table->string('preview_image', 255)->nullable();
                $table->string('access_type', 20)->default('free'); // 'free' | 'paid'
                $table->unsignedBigInteger('price')->default(0); // تومان
                $table->unsignedBigInteger('discount_price')->nullable();
                $table->string('current_version', 32)->default('1.0.0');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 2. Button Presets inside packs
        if (!Schema::hasTable('canvas_button_presets')) {
            Schema::create('canvas_button_presets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pack_id')->constrained('canvas_button_packs')->cascadeOnDelete();
                $table->string('slug', 120);
                $table->string('title', 191);
                $table->json('css_tokens');
                $table->text('preview_html')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['pack_id', 'slug'], 'canvas_button_presets_pack_slug_unique');
            });
        }

        // 3. Icon Packs
        if (!Schema::hasTable('canvas_icon_packs')) {
            Schema::create('canvas_icon_packs', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 120)->unique();
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->string('category_label', 120)->default('عمومی');
                $table->string('preview_image', 255)->nullable();
                $table->string('access_type', 20)->default('free'); // 'free' | 'paid'
                $table->unsignedBigInteger('price')->default(0); // تومان
                $table->unsignedBigInteger('discount_price')->nullable();
                $table->string('current_version', 32)->default('1.0.0');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 4. Icon Items inside packs
        if (!Schema::hasTable('canvas_icon_items')) {
            Schema::create('canvas_icon_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('pack_id')->constrained('canvas_icon_packs')->cascadeOnDelete();
                $table->string('slug', 120);
                $table->string('title', 191);
                $table->json('keywords')->nullable();
                $table->longText('svg_content');
                $table->string('view_box', 60)->default('0 0 24 24');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['pack_id', 'slug'], 'canvas_icon_items_pack_slug_unique');
            });
        }

        // 5. Header Presets
        if (!Schema::hasTable('canvas_header_presets')) {
            Schema::create('canvas_header_presets', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 120)->unique();
                $table->string('title', 191);
                $table->text('description')->nullable();
                $table->string('category_label', 120)->default('ناوبری');
                $table->string('preview_image', 255)->nullable();
                $table->string('access_type', 20)->default('free'); // 'free' | 'paid'
                $table->unsignedBigInteger('price')->default(0); // تومان
                $table->unsignedBigInteger('discount_price')->nullable();
                $table->string('current_version', 32)->default('1.0.0');
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        // 6. Unified Versioning & Changelog
        if (!Schema::hasTable('canvas_asset_versions')) {
            Schema::create('canvas_asset_versions', function (Blueprint $table) {
                $table->id();
                $table->string('asset_kind', 32); // 'motion', 'header', 'button_pack', 'icon_pack'
                $table->string('asset_slug', 120);
                $table->string('version', 32);
                $table->text('changelog')->nullable();
                $table->json('snapshot_summary')->nullable();
                $table->unsignedBigInteger('published_by_admin_id')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->index(['asset_kind', 'asset_slug'], 'canvas_asset_versions_kind_slug_idx');
                $table->unique(['asset_kind', 'asset_slug', 'version'], 'canvas_asset_versions_unique');
            });
        }

        // 7. Add commercial fields to motion_graphics if not existing
        if (Schema::hasTable('motion_graphics')) {
            Schema::table('motion_graphics', function (Blueprint $table) {
                if (!Schema::hasColumn('motion_graphics', 'access_type')) {
                    $table->string('access_type', 20)->default('free')->after('visibility');
                }
                if (!Schema::hasColumn('motion_graphics', 'price')) {
                    $table->unsignedBigInteger('price')->default(0)->after('access_type');
                }
                if (!Schema::hasColumn('motion_graphics', 'discount_price')) {
                    $table->unsignedBigInteger('discount_price')->nullable()->after('price');
                }
                if (!Schema::hasColumn('motion_graphics', 'current_version')) {
                    $table->string('current_version', 32)->default('1.0.0')->after('discount_price');
                }
            });
        }

        // 8. Add granted_version to site_asset_grants if not existing
        if (Schema::hasTable('site_asset_grants')) {
            Schema::table('site_asset_grants', function (Blueprint $table) {
                if (!Schema::hasColumn('site_asset_grants', 'granted_version')) {
                    $table->string('granted_version', 32)->nullable()->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('site_asset_grants') && Schema::hasColumn('site_asset_grants', 'granted_version')) {
            Schema::table('site_asset_grants', function (Blueprint $table) {
                $table->dropColumn('granted_version');
            });
        }

        if (Schema::hasTable('motion_graphics')) {
            Schema::table('motion_graphics', function (Blueprint $table) {
                $columns = ['access_type', 'price', 'discount_price', 'current_version'];
                $existing = array_filter($columns, fn ($col) => Schema::hasColumn('motion_graphics', $col));
                if (!empty($existing)) {
                    $table->dropColumn($existing);
                }
            });
        }

        Schema::dropIfExists('canvas_asset_versions');
        Schema::dropIfExists('canvas_header_presets');
        Schema::dropIfExists('canvas_icon_items');
        Schema::dropIfExists('canvas_icon_packs');
        Schema::dropIfExists('canvas_button_presets');
        Schema::dropIfExists('canvas_button_packs');
    }
};
