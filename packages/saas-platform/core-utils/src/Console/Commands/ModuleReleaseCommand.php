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
 *  3. Builds and registers the deployable package artifact
 *     (Phase 17-G — Part 6: chains `module:package-build`, previously a
 *     separate manual step; also runs ModulePackageValidator, Phase 17-F)
 *  4. Generates a canonical release manifest JSON
 *  5. Creates a git tag for the version
 *
 * This package never touches App\Models or the host adapter directly —
 * it only depends on the contract, resolved from the container. Step 3
 * is invoked by command name (`$this->call('module:package-build', ...)`),
 * the same loose-coupling pattern already used for Step 1's `module:build`
 * call — this class never references the host app's
 * App\Services\ModulePackageBuilderService directly.
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
        {version? : Version to release. If given and different from module.json, module.json is bumped to match before releasing — keeping the two from drifting (see the version-authority ADR).}
        {--dry-run : Prepare manifest but skip git tag creation}
        {--no-build : Skip module:build validation (not recommended)}
        {--no-package : Skip building the deployable package artifact (module:package-build)}
        {--force-package : Rebuild/overwrite the package artifact if one is already registered for this version}
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

        // Phase 17-G — Part 6: an explicit {version} argument bumps
        // module.json to match *before* anything else runs, so the
        // package this command eventually builds always has the same
        // version baked into its module.json as the module_releases row
        // it's attached to — exactly what ModulePackageValidator (17-F)
        // checks. Never left to drift.
        $requestedVersion = $this->argument('version');
        if ($requestedVersion !== null && $requestedVersion !== $version) {
            $this->writeModuleJsonVersion($data['_path'], $requestedVersion);
            $registry->flush();
            $data = $registry->get($module) ?? $data;
            $version = $data['version'] ?? $requestedVersion;
            $this->line("module.json version bumped to match requested release version: {$version}");
        }

        $this->line('');
        $this->line("🚀 Releasing module: <info>{$module}</info> v{$version}");
        $this->line('');

        // Step 1: Build validation
        if (!$this->option('no-build')) {
            $this->line('Step 1/5: Running module:build validation...');
            $exitCode = $this->call('module:build', ['module' => $module]);
            if ($exitCode !== 0) {
                $this->error('Release aborted — module:build failed.');
                return self::FAILURE;
            }
        } else {
            $this->warn('Step 1/5: Skipping module:build (--no-build flag set).');
        }

        // Read fresh version — build validation does not mutate module.json,
        // but the registry cache may predate an out-of-band version bump.
        $registry->flush();
        $data    = $registry->get($module) ?? $data;
        $version = $data['version'] ?? $version;

        // Step 2: Record the release against the platform's version authority.
        // Only the contract is used here — this package never sees
        // App\Models or the host adapter (see class docblock).
        //
        // Phase 17-H — Part 2/6: status starts at 'draft', not published.
        // Building a package (Step 3 below) and tagging (Step 5) still
        // happen — those are release-preparation, not a Marketplace
        // availability decision — but the release stays undeployable
        // until a human runs `module:publish {module} {version}` and it
        // passes the publishing contract (Part 3).
        $this->line('Step 2/5: Recording release in platform database...');
        $result = app(ModuleReleaseProducerInterface::class)->record(new ModuleReleaseRequest(
            slug: $module,
            version: $version,
            releaseType: $releaseType,
            changelog: $changelog,
            distributedAt: new \DateTimeImmutable(),
            status: 'draft',
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

        // Step 3: Build and register the deployable package artifact.
        // Phase 17-G — Part 6: previously a separate manual step
        // (`module:package-build`) a developer had to remember to run
        // after this command. --dry-run's documented meaning is "skip git
        // tag creation" only (Step 5) — packaging is a safe, local,
        // idempotent write (a ZIP under storage/ + a DB row) with no
        // external side effect like a tag, so it still runs under
        // --dry-run; only --no-package skips it. A failure here does NOT
        // undo the release row created in Step 2 — the release metadata
        // is still valid even without a package yet; re-run
        // `module:package-build` directly to retry.
        if ($this->option('no-package')) {
            $this->warn('Step 3/5: Skipping package build (--no-package flag set).');
        } else {
            $this->line('Step 3/5: Building package artifact...');
            $packageArgs = ['module' => $module, 'version' => $version];
            if ($this->option('force-package')) {
                $packageArgs['--force'] = true;
            }
            $exitCode = $this->call('module:package-build', $packageArgs);
            if ($exitCode !== 0) {
                $this->error('Package build failed — release row was recorded, but no deployable artifact exists yet.');
                $this->error("Retry with: php artisan module:package-build {$module} {$version}");
                return self::FAILURE;
            }
        }

        // Step 4: Generate release manifest
        $this->line('Step 4/5: Generating release manifest...');
        $manifest = app(ReleaseManifest::class)->generate($module, $data);
        $this->info("  Manifest written → storage/app/modules/releases/{$module}.json");
        $this->line("  Checksum: {$manifest['checksum']}");

        // Step 5: Git tag
        $tag = "module-{$module}-v{$version}";
        if ($this->option('dry-run')) {
            $this->warn("Step 5/5: [DRY RUN] Would create git tag: {$tag}");
        } else {
            $this->line("Step 5/5: Creating git tag: <comment>{$tag}</comment>...");

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

    /**
     * Mirrors ModuleVersionCommand::writeJson()'s exact pattern — sets an
     * absolute version rather than incrementing one.
     */
    private function writeModuleJsonVersion(string $modulePath, string $version): void
    {
        $path = "{$modulePath}/module.json";
        $json = json_decode(file_get_contents($path), true) ?? [];
        $json['version'] = $version;
        file_put_contents(
            $path,
            json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );

        $composerPath = "{$modulePath}/composer.json";
        if (file_exists($composerPath)) {
            $composerJson = json_decode(file_get_contents($composerPath), true) ?? [];
            $composerJson['version'] = $version;
            file_put_contents(
                $composerPath,
                json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
            );
        }
    }
}
