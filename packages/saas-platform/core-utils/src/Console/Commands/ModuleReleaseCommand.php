<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;
use SaaSPlatform\CoreUtils\Support\ReleaseManifest;

/**
 * PHASE 6.98 — TASK 2: module:release
 *
 * Prepares a module release:
 *  1. Validates the module passes module:build
 *  2. Generates a canonical release manifest JSON
 *  3. Creates a git tag for the version
 *
 * DOES NOT push tags or deploy. Release preparation only.
 */
class ModuleReleaseCommand extends Command
{
    protected $signature = 'module:release
        {module : The module slug to release}
        {--dry-run : Prepare manifest but skip git tag creation}
        {--no-build : Skip module:build validation (not recommended)}';

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

        $version = $data['version'] ?? '0.0.0';
        $this->line('');
        $this->line("🚀 Releasing module: <info>{$module}</info> v{$version}");
        $this->line('');

        // Step 1: Build validation
        if (!$this->option('no-build')) {
            $this->line('Step 1/3: Running module:build validation...');
            $exitCode = $this->call('module:build', ['module' => $module]);
            if ($exitCode !== 0) {
                $this->error('Release aborted — module:build failed.');
                return self::FAILURE;
            }
        } else {
            $this->warn('Step 1/3: Skipping module:build (--no-build flag set).');
        }

        // Step 2: Generate release manifest
        $this->line('Step 2/3: Generating release manifest...');
        $manifest = app(ReleaseManifest::class)->generate($module, $data);
        $this->info("  Manifest written → storage/app/modules/releases/{$module}.json");
        $this->line("  Checksum: {$manifest['checksum']}");

        // Step 3: Git tag
        $tag = "module-{$module}-v{$version}";
        if ($this->option('dry-run')) {
            $this->warn("Step 3/3: [DRY RUN] Would create git tag: {$tag}");
        } else {
            $this->line("Step 3/3: Creating git tag: <comment>{$tag}</comment>...");

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
