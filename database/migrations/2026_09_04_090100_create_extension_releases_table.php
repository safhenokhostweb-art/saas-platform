<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 3G-B-1 — Commercial Extension Release Foundation.
 *
 * extension_releases is the append-only commercial release ledger for
 * Extension products, mirroring theme_releases' shape and immutability
 * contract exactly (ADR-045 point 8): every column except `status` is
 * enforced immutable at the model layer (ExtensionRelease::booted()), rows
 * are never deleted through Eloquent.
 *
 * manifest is persisted as JSON with deliberately minimal, honest structural
 * validation only (ExtensionReleaseService::assertValidManifest()) — the
 * canonical ExtensionManifest DTO/schema is deferred to 3G-B-2 and must not
 * be duplicated here (ADR-045 point 10).
 *
 * restrictOnDelete on extension_id, not cascadeOnDelete: mirrors
 * theme_releases' own reasoning — Extension has no SoftDeletes trait, so a
 * master row with real release history must not be casually hard-deletable;
 * an Extension with zero releases can still be deleted freely.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('extension_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extension_id')->constrained()->restrictOnDelete();
            $table->string('version', 32); // semver, e.g. "1.0.0"
            $table->unsignedInteger('sdk_contract_version');
            $table->json('manifest');
            $table->string('status', 20)->default('published'); // published, superseded, deprecated
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['extension_id', 'version']);
            $table->index(['extension_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extension_releases');
    }
};
