<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3G-B-1 — Commercial Extension Product Foundation.
 *
 * extensions is the master commercial identity row for an Extension product —
 * global/catalog scope only (no site_id, no ownership-type column: a
 * customer-specific Extension is deliberately out of scope for this table by
 * construction, per ADR-045 point 19). Mirrors Theme's master-row shape:
 * identity + display metadata + a denormalized current_release_id pointer
 * that only ExtensionReleaseService may move (see the follow-up migration).
 *
 * vendor_slug/package_slug together form the authoritative product identity
 * "{vendor-slug}/{package-slug}" (ADR-045 point 5) — validated at the
 * application layer (ExtensionProductIdentity), enforced unique here.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('extensions', function (Blueprint $table) {
            $table->id();
            $table->string('vendor_slug', 40);
            $table->string('package_slug', 60);
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['vendor_slug', 'package_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extensions');
    }
};
