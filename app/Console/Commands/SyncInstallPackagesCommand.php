<?php

namespace App\Console\Commands;

use App\Models\CustomerInstallationPackage;
use Illuminate\Console\Command;

class SyncInstallPackagesCommand extends Command
{
    protected $signature = 'customer-install:sync-packages
                            {--activate : Automatically activate the highest valid non-stale package}
                            {--path= : Directory to scan (defaults to storage/app/install-packages)}';

    protected $description = 'Scan and synchronize local customer-site installation packages in database';

    public function handle(): int
    {
        $dir = $this->option('path') ?: storage_path('app/install-packages');

        if (!is_dir($dir)) {
            $this->error("Directory not found: {$dir}");
            return 1;
        }

        $files = glob($dir . '/customer-site-v*.zip');
        if (empty($files)) {
            $this->warn("No customer-site ZIP packages found in {$dir}");
            return 1;
        }

        $records = [];
        $latestValid = null;

        foreach ($files as $file) {
            if (!preg_match('/customer-site-v(\d+\.\d+\.\d+.*)\.zip$/i', basename($file), $m)) {
                continue;
            }

            $version = $m[1];
            $sha256 = hash_file('sha256', $file);
            $sizeBytes = filesize($file);

            $pkg = CustomerInstallationPackage::where('version', $version)->first();
            if ($pkg) {
                $pkg->update([
                    'local_path' => $file,
                    'sha256'     => $sha256,
                    'size_bytes' => $sizeBytes,
                ]);
            } else {
                $pkg = CustomerInstallationPackage::create([
                    'version'       => $version,
                    'arvan_key'     => '',
                    'local_path'    => $file,
                    'sha256'        => $sha256,
                    'size_bytes'    => $sizeBytes,
                    'released_at'   => now(),
                    'is_active'     => false,
                    'release_notes' => 'Synchronized from local package directory',
                ]);
            }

            $isStale = $pkg->isStale();
            if (!$isStale) {
                if ($latestValid === null || version_compare($pkg->version, $latestValid->version, '>')) {
                    $latestValid = $pkg;
                }
            }

            $records[] = $pkg;
        }

        if ($this->option('activate') && $latestValid !== null) {
            $latestValid->activate();
            $this->info("Activated latest valid package: v{$latestValid->version}");
        }

        $tableData = [];
        foreach ($records as $pkg) {
            $pkg->refresh();
            $tableData[] = [
                $pkg->version,
                substr($pkg->sha256, 0, 16) . '...',
                $pkg->sizeHuman(),
                $pkg->is_active ? 'YES' : 'no',
                $pkg->isStale() ? 'STALE (< ' . CustomerInstallationPackage::MINIMUM_SUPPORTED_VERSION . ')' : 'VALID',
            ];
        }

        $this->table(['Version', 'SHA-256', 'Size', 'Active', 'Support Status'], $tableData);

        $active = CustomerInstallationPackage::active();
        if ($active === null) {
            $this->error('Warning: No active package is configured! Use --activate or run customer-install:register-local-package --activate');
            return 1;
        }

        if ($active->isStale()) {
            $this->error("Warning: Currently active package v{$active->version} is STALE! Minimum required: " . CustomerInstallationPackage::MINIMUM_SUPPORTED_VERSION);
            return 1;
        }

        return 0;
    }
}
