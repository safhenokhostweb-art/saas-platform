<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-087: the platform's per-asset override of the access policy the
 * theme-runtime package declares (resources/access/policy.json). No row =
 * the declared rule. A row replaces that rule whole (is_public,
 * required_vertical, entitlement_keys, locked_display) for every site, and
 * is shipped to tenants in the provisioning payload, so the owner can
 * open or close one asset without a release.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('platform_asset_policies')) {
            return;
        }
        Schema::create('platform_asset_policies', function (Blueprint $table) {
            $table->id();
            $table->string('asset_kind', 32);
            $table->string('asset_key', 120);
            $table->boolean('is_public')->default(false);
            $table->string('required_vertical', 64)->nullable();
            $table->json('entitlement_keys')->nullable();
            $table->string('locked_display', 16)->default('hidden'); // teaser | hidden
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['asset_kind', 'asset_key'], 'platform_asset_policies_asset_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_asset_policies');
    }
};
