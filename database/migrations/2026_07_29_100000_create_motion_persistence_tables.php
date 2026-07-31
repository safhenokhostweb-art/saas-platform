<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3 — Motion Graphics persistence foundation.
 *
 * Host-owned bookkeeping of "which site has which Motion asset at which
 * version" — never a duplicate of the standalone module's own ownership
 * data. Every reference to a Motion asset here is an opaque `asset_id`
 * (the module's MotionGraphic.uuid) + integer version; there is no
 * foreign key into the module's own tables (motion_graphics,
 * motion_graphic_versions, ...), and the module's tables are not touched
 * by this migration. See docs/motion-persistence/MIGRATION_DECISION.md.
 *
 * Safety:
 *  - Additive only — five new tables, nothing else changes.
 *  - Forward-only content (versions/activations/snapshots are
 *    append-only at the application layer — see PackageSnapshot and
 *    AssetActivation models), but the migration itself is fully
 *    reversible (down() drops exactly what up() created).
 *  - Foreign keys to existing host tables (sites) use restrictOnDelete;
 *    nothing here can cascade-delete a site. Foreign keys between the
 *    new tables use cascadeOnDelete because child rows have no meaning
 *    without their parent (mirrors module_entitlements/module_licenses
 *    conventions already used in this codebase).
 *  - No data is assumed or back-filled; every table starts empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motion_template_asset_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('template_reference', 191);
            $table->json('required_assets');
            $table->json('optional_assets')->nullable();
            $table->json('compatibility_rules')->nullable();
            $table->unsignedInteger('package_version')->default(1);
            $table->string('created_by_actor_identifier', 191)->nullable();
            $table->timestamps();

            $table->unique(['template_reference', 'package_version'], 'motion_tap_ref_version_unique');
        });

        Schema::create('motion_site_asset_packages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            // Denormalized copy of the bound TemplateAssetPackage's
            // template_reference — the natural key for "this site's one
            // binding to this template". template_asset_package_id is the
            // CURRENT POINTER within that binding and moves forward on
            // upgrade (see docs/motion-persistence/VERSION_BINDING_RULES.md);
            // template_reference itself never changes for a given row.
            $table->string('template_reference', 191);
            $table->foreignId('template_asset_package_id')
                ->constrained('motion_template_asset_packages')
                ->restrictOnDelete();
            $table->string('brand_variant_reference', 191)->nullable();
            $table->string('activation_status', 16)->default('pending'); // pending|active|rolled_back|retired
            $table->timestamp('activated_at')->nullable();
            $table->string('created_by_actor_identifier', 191)->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'template_reference'], 'motion_sap_site_template_ref_unique');
            $table->index('site_id', 'motion_sap_site_idx');
            $table->index('template_asset_package_id', 'motion_sap_template_package_idx');
        });

        Schema::create('motion_site_asset_package_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('site_asset_package_id')
                ->constrained('motion_site_asset_packages')
                ->cascadeOnDelete();
            $table->string('asset_id', 191);
            $table->unsignedInteger('active_version');
            $table->string('bound_checksum', 191);
            $table->boolean('is_active')->default(true);
            $table->timestamp('installed_at');
            $table->timestamps();

            $table->unique(['site_asset_package_id', 'asset_id'], 'motion_sapi_package_asset_unique');
            $table->index('asset_id', 'motion_sapi_asset_idx');
        });

        Schema::create('motion_asset_activations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('site_asset_package_item_id')
                ->constrained('motion_site_asset_package_items')
                ->cascadeOnDelete();
            $table->string('asset_id', 191);
            $table->unsignedInteger('version');
            $table->unsignedInteger('previous_version')->nullable();
            $table->string('activation_type', 16); // install|upgrade|rollback
            $table->timestamp('activated_at');
            $table->string('actor_identifier', 191)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('site_asset_package_item_id', 'motion_activation_item_idx');
            $table->index(['asset_id', 'version'], 'motion_activation_asset_version_idx');
        });

        Schema::create('motion_package_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('site_asset_package_id')
                ->constrained('motion_site_asset_packages')
                ->cascadeOnDelete();
            $table->json('snapshot');
            $table->string('checksum', 191);
            $table->timestamp('captured_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index('site_asset_package_id', 'motion_snapshot_package_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motion_package_snapshots');
        Schema::dropIfExists('motion_asset_activations');
        Schema::dropIfExists('motion_site_asset_package_items');
        Schema::dropIfExists('motion_site_asset_packages');
        Schema::dropIfExists('motion_template_asset_packages');
    }
};
