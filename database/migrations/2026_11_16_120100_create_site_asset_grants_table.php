<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-087: one platform asset (asset_kind + asset_key, the AssetRef) unlocked
 * for one site, by an admin grant, a purchase or a licence. One row per
 * (site, asset); revoking keeps the row with who/when, and re-granting
 * re-activates it, so the history of the last decision is never lost.
 * Motions keep their own ADR-086 table (motion_site_asset_grants).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('site_asset_grants')) {
            return;
        }
        Schema::create('site_asset_grants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('site_id');
            $table->foreign('site_id')->references('id')->on('sites')->onDelete('cascade');
            $table->string('asset_kind', 32);
            $table->string('asset_key', 120);
            $table->string('status', 16)->default('active');      // active | revoked
            $table->string('source', 24)->default('admin_grant'); // admin_grant | purchase | license
            $table->string('reference', 191)->nullable();           // order / licence reference
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->timestamp('granted_at')->nullable();
            $table->unsignedBigInteger('revoked_by')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'asset_kind', 'asset_key'], 'site_asset_grants_site_asset_unique');
            $table->index(['asset_kind', 'asset_key', 'status'], 'site_asset_grants_asset_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_asset_grants');
    }
};
