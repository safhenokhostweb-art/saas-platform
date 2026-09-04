<?php

namespace App\Services\ThemeBuilder\Extension;

use App\Models\Extension;
use App\Models\ExtensionRelease;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phase 3G-B-1 — Commercial Extension Product/Release Foundation (ADR-045).
 *
 * The sole writer of `extensions` and `extension_releases`. Mirrors the
 * guarantees ThemeReleaseService already gives Theme Products: transactional,
 * monotonic-version-enforced (via a duplicate-version guard), append-only,
 * single designated writer. No controller/command/test creates an Extension
 * or ExtensionRelease row directly.
 *
 * Deliberately narrow for this phase: no readiness checks, no dependency
 * extraction, no quality gate — those belong to later 3G-B phases once a
 * Theme can actually declare a dependency on an Extension (3G-B-3 onward).
 */
class ExtensionReleaseService
{
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
        $this->assertVersionNotAlreadyReleased($extension, $version);

        return DB::transaction(function () use ($extension, $version, $sdkContractVersion, $manifest, $publishedBy) {
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
                'manifest' => $manifest,
                'status' => ExtensionRelease::STATUS_PUBLISHED,
                'published_by' => $publishedBy,
                'published_at' => now(),
            ]);

            $extension->current_release_id = $release->id;
            $extension->save();

            return $release->fresh();
        });
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
     * Deliberately minimal, honest structural validation only (ADR-045
     * point 10) — no schema is enforced here. The canonical
     * ExtensionManifest DTO/schema arrives in 3G-B-2; this method must
     * never grow into a second, competing manifest schema before then.
     */
    private function assertValidManifest(array $manifest): void
    {
        if ($manifest === [] || array_is_list($manifest)) {
            throw new InvalidArgumentException('Manifest must be a non-empty, object-shaped array (a JSON object, not a bare list).');
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
