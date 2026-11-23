<?php

namespace App\Console\Commands;

use App\Services\CustomerInstallation\CustomerPackageSyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncCustomerPackageFromReleaseCommand extends Command
{
    protected $signature = 'customer-install:sync-from-release
                            {--source= : Absolute path to the deployed customer-site directory (defaults to config or /var/www/customer-site)}
                            {--commit= : Exact commit SHA of the deployed release}
                            {--package-version= : Optional semantic version string (e.g. 1.1.1)}
                            {--notes= : Custom release notes}
                            {--force : Force repackaging even if package for this commit is already active}';

    protected $description = 'Atomically package the latest deployed customer-site into saas-platform install-packages and activate it';

    public function handle(CustomerPackageSyncService $syncService): int
    {
        $sourcePath = $this->option('source') ?: config(
            'customer_installation.package.customer_site_source_path',
            '/var/www/customer-site'
        );
        $commit = $this->option('commit');
        $version = $this->option('package-version');
        $notes = $this->option('notes');
        $force = (bool) $this->option('force');

        $this->info("CustomerPackageSync: Synchronizing customer-site package from '{$sourcePath}'...");

        try {
            $package = $syncService->syncFromDeployedSite(
                sourcePath: $sourcePath,
                commit: $commit,
                explicitVersion: $version,
                notes: $notes,
                force: $force
            );

            $this->info("Customer installation package synchronized successfully!");
            $this->table(
                ['Version', 'Active', 'SHA-256', 'Size', 'Local Path'],
                [[
                    $package->version,
                    $package->is_active ? 'YES' : 'NO',
                    substr((string) $package->sha256, 0, 16) . '...',
                    $package->sizeHuman(),
                    $package->local_path,
                ]]
            );

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("✗ Failed to synchronize customer installation package: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
