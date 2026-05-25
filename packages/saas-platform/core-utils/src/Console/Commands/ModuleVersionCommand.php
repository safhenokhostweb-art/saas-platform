<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;

/**
 * PHASE 6.98 — TASK 2: module:version
 *
 * Reads or increments the version of an installed module.
 * Updates both module.json and composer.json when bumping.
 */
class ModuleVersionCommand extends Command
{
    protected $signature = 'module:version
        {module : The module slug (e.g. forms, tickets)}
        {--patch : Increment patch version (1.0.x)}
        {--minor : Increment minor version (1.x.0)}
        {--major : Increment major version (x.0.0)}';

    protected $description = 'Read or increment the version of a module';

    public function handle(): int
    {
        $module   = $this->argument('module');
        $registry = app(ModuleVersionRegistry::class);
        $data     = $registry->get($module);

        if (!$data) {
            $this->error("Module [{$module}] not found. Run `php artisan module:list` to see available modules.");
            return self::FAILURE;
        }

        $currentVersion = $data['version'] ?? '0.0.0';
        $modulePath     = $data['_path'];

        // No flag → display only
        if (!$this->option('patch') && !$this->option('minor') && !$this->option('major')) {
            $this->table(['Field', 'Value'], [
                ['Module',        $module],
                ['Version',       $currentVersion],
                ['Schema Version', $data['schema_version'] ?? '1'],
                ['Source',        $data['_source']],
                ['Path',          $modulePath],
            ]);
            return self::SUCCESS;
        }

        $newVersion = $this->increment($currentVersion);

        // Update module.json
        $this->writeJson($modulePath . '/module.json', function (array $json) use ($newVersion) {
            $json['version'] = $newVersion;
            return $json;
        });

        // Update composer.json version field if present
        $composerPath = $modulePath . '/composer.json';
        if (file_exists($composerPath)) {
            $this->writeJson($composerPath, function (array $json) use ($newVersion) {
                $json['version'] = $newVersion;
                return $json;
            });
        }

        $registry->flush();

        $this->info("Version bumped: {$currentVersion} → <comment>{$newVersion}</comment>");
        $this->line("  module.json updated.");
        return self::SUCCESS;
    }

    protected function increment(string $version): string
    {
        $parts = explode('.', $version . '.0.0');
        [$major, $minor, $patch] = array_map('intval', array_slice($parts, 0, 3));

        if ($this->option('major')) {
            return ($major + 1) . '.0.0';
        }
        if ($this->option('minor')) {
            return "{$major}." . ($minor + 1) . '.0';
        }
        return "{$major}.{$minor}." . ($patch + 1);
    }

    protected function writeJson(string $path, callable $transform): void
    {
        $json = json_decode(file_get_contents($path), true) ?? [];
        $json = $transform($json);
        file_put_contents(
            $path,
            json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }
}
