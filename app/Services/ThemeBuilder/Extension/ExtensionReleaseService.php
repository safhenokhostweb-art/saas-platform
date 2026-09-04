<?php

namespace App\Services\ThemeBuilder\Extension;

use App\Models\Extension;
use App\Models\ExtensionRelease;
use App\Services\ThemeBuilder\Dependency\ExtensionElementOwnershipResolver;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use SaaSPlatform\ExtensionSdk\ExtensionManifest;
use SaaSPlatform\ExtensionSdk\ExtensionProductIdentity;
use SaaSPlatform\ExtensionSdk\ExtensionSdkException;

/**
 * Phase 3G-B-1 — Commercial Extension Product/Release Foundation (ADR-045).
 * Phase 3G-B-2 — manifest validation/identity now delegate to the shared
 * SaaSPlatform\ExtensionSdk package (ExtensionProductIdentity/
 * ExtensionManifest), replacing the prior local duplicate identity grammar
 * and the prior structural-only (non-empty, non-list) manifest check.
 * Phase 3G-B-3 ownership closure (ADR-046) — enforces the durable, global,
 * permanent, non-transferable element-type ownership invariant at
 * publication time (see assertNoElementOwnershipConflict()).
 *
 * The sole writer of `extensions` and `extension_releases`. Mirrors the
 * guarantees ThemeReleaseService already gives Theme Products: transactional,
 * monotonic-version-enforced (via a duplicate-version guard), append-only,
 * single designated writer. No controller/command/test creates an Extension
 * or ExtensionRelease row directly.
 *
 * Deliberately narrow for this phase: no readiness checks, no dependency
 * extraction (beyond ownership-conflict enforcement), no quality gate —
 * those belong to later 3G-B phases once a Theme can actually declare a
 * dependency on an Extension.
 */
class ExtensionReleaseService
{
    /**
     * ADR-046 — a single GLOBAL lock key (not per-extension): the ownership
     * invariant spans every Extension product, so two concurrent
     * publications for two *different* products must still be serialized
     * against each other, not just two publications for the same one.
     * Reuses the exact Cache::lock() pattern already established by
     * App\Services\Modules\ModuleDeploymentRequestService for this identical
     * class of problem (a check-then-write critical section that a plain DB
     * transaction alone cannot make atomic, because the conflicting row
     * does not exist yet at scan time). Backed by Redis in every real
     * environment (config/cache.php default), giving genuine cross-process/
     * cross-worker mutual exclusion — never a fragile application-only
     * timing assumption.
     */
    private const OWNERSHIP_LOCK_KEY = 'extension-release-element-ownership-publish-lock';
    private const OWNERSHIP_LOCK_WAIT_SECONDS = 5;
    private const OWNERSHIP_LOCK_TTL_SECONDS = 10;
    public function createExtension(string $vendorSlug, string $packageSlug, string $name, ?string $description = null): Extension
    {
        $this->assertValidVendorSlug($vendorSlug);
        $this->assertValidPackageSlug($packageSlug);

        if (Extension::where('vendor_slug', $vendorSlug)->where('package_slug', $packageSlug)->exists()) {
            throw new InvalidArgumentException(
                "Extension \"{$vendorSlug}/{$packageSlug}\" already exists."
            );
        }

        return Extension::create([
            'vendor_slug' => $vendorSlug,
            'package_slug' => $packageSlug,
            'name' => $name,
            'description' => $description,
        ]);
    }

    /**
     * Freeze a new immutable release for an Extension and make it the
     * Extension's current release. Mirrors ThemeReleaseService::release():
     * whatever is currently PUBLISHED is superseded via the pointer inside
     * the same transaction, never mutated in place.
     *
     * $manifest may optionally self-describe its own "product_identity" /
     * "package_version" / "sdk_contract_version" (the shared ExtensionSdk
     * ExtensionManifest wire format — see toArray()); when present, each
     * must exactly equal this call's own $extension/$version/
     * $sdkContractVersion, or the release is rejected (fail closed, never
     * silently rewritten — ADR-045/3G-B-2). When absent, they are filled in
     * from this call's own canonical values, so a caller may still pass a
     * bare `{"elements": [...]}` manifest as before.
     *
     * ADR-046 — additionally enforces the global Extension element-type
     * ownership invariant: every element type this manifest declares must
     * either be new (no product has ever claimed it) or already belong to
     * THIS Extension product (same-product reuse across releases is always
     * allowed). A different product's historical claim — regardless of
     * that claim's current/superseded/deprecated status — fails the whole
     * publication closed, atomically, under a global lock (see
     * OWNERSHIP_LOCK_KEY doc comment) so two concurrent publications for
     * two different products can never both "win" a race on the same type.
     */
    public function release(
        Extension $extension,
        string $version,
        int $sdkContractVersion,
        array $manifest,
        ?int $publishedBy = null
    ): ExtensionRelease {
        $this->assertValidSemver($version);
        $this->assertValidSdkContractVersion($sdkContractVersion);
        $this->assertValidManifest($manifest);
        $validatedManifest = $this->buildValidatedManifest($extension, $version, $sdkContractVersion, $manifest);
        $this->assertVersionNotAlreadyReleased($extension, $version);

        $lock = Cache::lock(self::OWNERSHIP_LOCK_KEY, self::OWNERSHIP_LOCK_TTL_SECONDS);

        try {
            return $lock->block(
                self::OWNERSHIP_LOCK_WAIT_SECONDS,
                function () use ($extension, $version, $sdkContractVersion, $validatedManifest, $publishedBy) {
                    return DB::transaction(function () use ($extension, $version, $sdkContractVersion, $validatedManifest, $publishedBy) {
                        // Re-checked here, INSIDE the lock and the transaction —
                        // never only before acquiring the lock — so no other
                        // publication can slip a conflicting claim in between
                        // the pre-check and this one (ADR-046).
                        $this->assertNoElementOwnershipConflict($extension, $validatedManifest);

                        // currentRelease() (method, not the cached relation) so an
                        // $extension instance reused across multiple release() calls
                        // always sees the live pointer.
                        $current = $extension->currentRelease()->first();
                        if ($current && $current->status === ExtensionRelease::STATUS_PUBLISHED) {
                            $current->update(['status' => ExtensionRelease::STATUS_SUPERSEDED]);
                        }

                        $release = $extension->releases()->create([
                            'version' => $version,
                            'sdk_contract_version' => $sdkContractVersion,
                            'manifest' => $validatedManifest->toArray(),
                            'status' => ExtensionRelease::STATUS_PUBLISHED,
                            'published_by' => $publishedBy,
                            'published_at' => now(),
                        ]);

                        $extension->current_release_id = $release->id;
                        $extension->save();

                        return $release->fresh();
                    });
                }
            );
        } catch (LockTimeoutException $e) {
            throw new RuntimeException(
                'Could not acquire the Extension release ownership lock in time — another Extension release is publishing concurrently. Please retry.',
                previous: $e
            );
        }
    }

    /**
     * ADR-046 — the ONE enforcement point for the global, permanent,
     * non-transferable Extension element-type ownership invariant. Reuses
     * ExtensionElementOwnershipResolver unchanged (never re-implements the
     * manifest scan) rather than adding a second, competing ownership query.
     *
     * For every element type this manifest declares:
     *   - zero prior owners (unresolved)      → allowed (first-ever claim)
     *   - this same product is the sole owner → allowed (same-product reuse)
     *   - any OTHER product has ever claimed  → rejected, fail closed,
     *     regardless of that claim's current/superseded/deprecated status
     *     and regardless of whether $extension's OWN current release still
     *     declares the type.
     */
    private function assertNoElementOwnershipConflict(Extension $extension, ExtensionManifest $manifest): void
    {
        $elementTypes = array_keys($manifest->elements);
        if ($elementTypes === []) {
            return;
        }

        $ownProductIdentity = $extension->identifier();
        $ownership = (new ExtensionElementOwnershipResolver())->resolve($elementTypes);

        foreach ($ownership as $type => $result) {
            $otherOwners = array_values(array_filter(
                $result->candidateProductIdentities,
                fn (string $identity) => $identity !== $ownProductIdentity
            ));

            if ($otherOwners !== []) {
                throw new InvalidArgumentException(
                    "Element type \"{$type}\" is already owned by a different Extension product "
                    . '(' . implode(', ', $otherOwners) . ") and can never be claimed by \"{$ownProductIdentity}\" — "
                    . 'Extension element type ownership is global, permanent, and non-transferable once first '
                    . 'published (ADR-046).'
                );
            }
        }
    }

    private function assertValidVendorSlug(string $vendorSlug): void
    {
        if (!ExtensionProductIdentity::isValidVendorSlug($vendorSlug)) {
            throw new InvalidArgumentException("Vendor slug \"{$vendorSlug}\" is not a valid Extension vendor identifier.");
        }
    }

    private function assertValidPackageSlug(string $packageSlug): void
    {
        if (!ExtensionProductIdentity::isValidPackageSlug($packageSlug)) {
            throw new InvalidArgumentException("Package slug \"{$packageSlug}\" is not a valid Extension package identifier.");
        }
    }

    private function assertValidSemver(string $version): void
    {
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            throw new InvalidArgumentException("Release version \"{$version}\" must be semver (e.g. 1.0.0).");
        }
    }

    private function assertValidSdkContractVersion(int $sdkContractVersion): void
    {
        if ($sdkContractVersion < 1) {
            throw new InvalidArgumentException("SDK contract version must be a positive integer, got {$sdkContractVersion}.");
        }
    }

    /**
     * Cheap, honest structural gate on the RAW caller-supplied manifest
     * (ADR-045 point 10) — non-empty, object-shaped, before it is ever
     * merged/parsed into a shared ExtensionManifest DTO below.
     */
    private function assertValidManifest(array $manifest): void
    {
        if ($manifest === [] || array_is_list($manifest)) {
            throw new InvalidArgumentException('Manifest must be a non-empty, object-shaped array (a JSON object, not a bare list).');
        }
    }

    /**
     * Phase 3G-B-2 (ADR-045 points 5, 10, 11, 19-21) — the real schema
     * enforcement point. Fails closed on any identity/version/sdk-contract
     * mismatch between the manifest's own self-description (if present) and
     * this call's canonical values; never silently rewrites either side.
     */
    private function buildValidatedManifest(Extension $extension, string $version, int $sdkContractVersion, array $manifest): ExtensionManifest
    {
        if (array_key_exists('product_identity', $manifest) && $manifest['product_identity'] !== $extension->identifier()) {
            throw new InvalidArgumentException(
                "Manifest product identity \"{$manifest['product_identity']}\" does not match Extension identity \"{$extension->identifier()}\"."
            );
        }

        if (array_key_exists('package_version', $manifest) && $manifest['package_version'] !== $version) {
            throw new InvalidArgumentException(
                "Manifest package version \"{$manifest['package_version']}\" does not match the declared release version \"{$version}\"."
            );
        }

        if (array_key_exists('sdk_contract_version', $manifest) && (int) $manifest['sdk_contract_version'] !== $sdkContractVersion) {
            throw new InvalidArgumentException(
                "Manifest SDK contract version \"{$manifest['sdk_contract_version']}\" does not match the declared release SDK contract version \"{$sdkContractVersion}\"."
            );
        }

        $manifest['product_identity'] = $extension->identifier();
        $manifest['package_version'] = $version;
        $manifest['sdk_contract_version'] = $sdkContractVersion;

        try {
            return ExtensionManifest::fromArray($manifest);
        } catch (ExtensionSdkException $e) {
            throw new InvalidArgumentException('Invalid Extension manifest: ' . $e->getMessage(), previous: $e);
        }
    }

    private function assertVersionNotAlreadyReleased(Extension $extension, string $version): void
    {
        if ($extension->releases()->where('version', $version)->exists()) {
            throw new InvalidArgumentException(
                "Extension \"{$extension->identifier()}\" already has a release for version \"{$version}\"."
            );
        }
    }
}
