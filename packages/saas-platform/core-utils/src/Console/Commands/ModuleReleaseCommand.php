<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Contracts\Services\Dto\ModuleReleaseRequest;
use SaaSPlatform\CoreUtils\Contracts\Services\Dto\ModuleReleaseResult;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleReleaseProducerInterface;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;
use SaaSPlatform\CoreUtils\Support\ReleaseManifest;

/**
 * PHASE 6.98 — TASK 2: module:release
 * Commit 12-G — wired to the platform's version authority.
 * Commit 12-M — optional customer-facing changelog input.
 *
 * Prepares a module release:
 *  1. Validates the module passes module:build
 *  2. Records the release against module_releases via
 *     ModuleReleaseProducerInterface (see
 *     docs/architecture/module-version-authority-adr.md)
 *  3. Generates a canonical release manifest JSON
 *  4. Creates a git tag for the version
 *
 * This package never touches App\Models or the host adapter directly —
 * it only depends on the contract, resolved from the container.
 *
 * DOES NOT push tags or deploy. Release preparation only.
 */
class ModuleReleaseCommand extends Command
{
    /**
     * --changelog is customer-facing text stored verbatim on the release
     * row and surfaced through the public update API. See
     * docs/architecture/module-release-changelog-convention.md for what
     * belongs in it before using this flag.
     */
    protected const CHANGELOG_MAX_LENGTH = 500;

    protected $signature = 'module:release
        {module : The module slug to release}
        {--dry-run : Prepare manifest but skip git tag creation}
        {--no-build : Skip module:build validation (not recommended)}
        {--type=patch : Release type recorded in module_releases (patch|minor|major)}
        {--changelog= : Customer-facing changelog text (see docs/architecture/module-release-changelog-convention.md)}';

    protected $description = 'Prepare a module release: generate manifest and create git tag';

    public function handle(): int
    {
        $module   = $this->argument('module');
        $registry = app(ModuleVersionRegistry::class);
        $data     = $registry->get($module);

        if (!$data) {
            $this->error("Module [{$module}] not found.");
            return self::FAILURE;
        }

        $releaseType = $this->option('type');
        if (!in_array($releaseType, ['patch', 'minor', 'major'], true)) {
            $this->error("Invalid --type [{$releaseType}]. Expected patch, minor, or major.");
            return self::FAILURE;
        }

        $changelog = $this->option('changelog');
        if ($changelog !== null) {
            $changelog = trim($changelog);
            if ($changelog === '') {
                $changelog = null;
            } elseif (mb_strlen($changelog) > self::CHANGELOG_MAX_LENGTH) {
                $this->error(
                    '--changelog must be ' . self::CHANGELOG_MAX_LENGTH . ' characters or fewer. '
                    . 'See docs/architecture/module-release-changelog-convention.md for guidance.'
                );
                return self::FAILURE;
            }
        }

        $version = $data['version'] ?? '0.0.0';
        $this->line('');
        $this->line("🚀 Releasing module: <info>{$module}</info> v{$version}");
        $this->line('');

        // Step 1: Build validation
        if (!$this->option('no-build')) {
            $this->line('Step 1/4: Running module:build validation...');
            $exitCode = $this->call('module:build', ['module' => $module]);
            if ($exitCode !== 0) {
                $this->error('Release aborted — module:build failed.');
                return self::FAILURE;
            }
        } else {
            $this->warn('Step 1/4: Skipping module:build (--no-build flag set).');
        }

        // Read fresh version — build validation does not mutate module.json,
        // but the registry cache may predate an out-of-band version bump.
        $registry->flush();
        $data    = $registry->get($module) ?? $data;
        $version = $data['version'] ?? $version;

        // Step 2: Record the release against the platform's version authority.
        // Only the contract is used here — this package never sees
        // App\Models or the host adapter (see class docblock).
        $this->line('Step 2/4: Recording release in platform database...');
        $result = app(ModuleReleaseProducerInterface::class)->record(new ModuleReleaseRequest(
            slug: $module,
            version: $version,
            releaseType: $releaseType,
            changelog: $changelog,
            distributedAt: new \DateTimeImmutable(),
        ));

        switch ($result->status) {
            case ModuleReleaseResult::STATUS_CREATED:
                $this->info("  Release recorded (module_releases id={$result->releaseId}).");
                break;
            case ModuleReleaseResult::STATUS_SKIPPED_DUPLICATE:
                $this->warn("  {$result->reason}");
                $this->warn('  Continuing — an active release for this version already exists.');
                break;
            case ModuleReleaseResult::STATUS_SKIPPED_NO_MODULE_ROW:
            case ModuleReleaseResult::STATUS_FAILED:
            default:
                $this->error("Release aborted — {$result->reason}");
                return self::FAILURE;
        }

        // Step 3: Generate release manifest
        $this->line('Step 3/4: Generating release manifest...');
        $manifest = app(ReleaseManifest::class)->generate($module, $data);
        $this->info("  Manifest written → storage/app/modules/releases/{$module}.json");
        $this->line("  Checksum: {$manifest['checksum']}");

        // Step 4: Git tag
        $tag = "module-{$module}-v{$version}";
        if ($this->option('dry-run')) {
            $this->warn("Step 4/4: [DRY RUN] Would create git tag: {$tag}");
        } else {
            $this->line("Step 4/4: Creating git tag: <comment>{$tag}</comment>...");

            // Collect checksums for tag message
            $tagMessage = "Release {$module} v{$version}\n\nSchema version: " . ($data['schema_version'] ?? '1');

            exec("git tag -a \"{$tag}\" -m \"{$tagMessage}\" 2>&1", $output, $exitCode);

            if ($exitCode !== 0) {
                $this->warn('Git tag failed (tag may already exist or git not available): ' . implode(' ', $output));
                $this->warn('Manifest was still generated successfully.');
            } else {
                $this->info("  Git tag created: {$tag}");
                $this->line("  Run <comment>git push origin {$tag}</comment> when ready to publish.");
            }
        }

        $this->line('');
        $this->info("Release preparation complete for {$module} v{$version}.");
        return self::SUCCESS;
    }
}
