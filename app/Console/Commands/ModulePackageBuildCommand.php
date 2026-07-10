<?php

namespace App\Console\Commands;

use App\Services\ModulePackageBuilderService;
use Illuminate\Console\Command;

/**
 * Phase 13-H — standalone, independently-testable package build step.
 * Does not touch CustomerInstallationOrchestrator or any pipeline — that
 * wiring is a later phase's responsibility.
 */
class ModulePackageBuildCommand extends Command
{
    protected $signature = 'module:package-build
        {module : The module slug}
        {version : The already-released version to package (must have a module_releases row)}
        {--force : Rebuild and overwrite an already-registered package}';

    protected $description = 'Build a deployable ZIP for an existing module release and register its artifact identity';

    public function handle(ModulePackageBuilderService $builder): int
    {
        $slug = $this->argument('module');
        $version = $this->argument('version');
        $force = (bool) $this->option('force');

        try {
            $result = $builder->build($slug, $version, $force);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Package built and registered for {$slug} v{$version}.");
        $this->table(
            ['module_release_id', 'package_path', 'package_sha256', 'package_size_bytes'],
            [[
                $result['module_release_id'],
                $result['package_path'],
                $result['package_sha256'],
                $result['package_size_bytes'],
            ]]
        );

        return self::SUCCESS;
    }
}
