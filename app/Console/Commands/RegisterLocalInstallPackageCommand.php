<?php

namespace App\Console\Commands;

use App\Models\CustomerInstallationPackage;
use Illuminate\Console\Command;

class RegisterLocalInstallPackageCommand extends Command
{
    protected $signature = 'customer-install:register-local-package
                            {path : Absolute path to the customer-site ZIP on this server}
                            {version : Semver e.g. 1.0.0}
                            {--activate : Mark as active immediately}
                            {--notes= : Release notes}';

    protected $description = 'Register a local customer-site ZIP as an install package (no Arvan required)';

    public function handle(): int
    {
        $path    = $this->argument('path');
        $version = $this->argument('version');

        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return 1;
        }

        if (!is_readable($path)) {
            $this->error("File is not readable: {$path}");
            return 1;
        }

        if (CustomerInstallationPackage::where('version', $version)->exists()) {
            $this->warn("Package version {$version} already registered.");
            return 1;
        }

        $this->info("Computing SHA-256 checksum (may take a moment)...");
        $sha256    = hash_file('sha256', $path);
        $sizeBytes = filesize($path);

        $pkg = CustomerInstallationPackage::create([
            'version'      => $version,
            'arvan_key'    => '',
            'local_path'   => $path,
            'sha256'       => $sha256,
            'size_bytes'   => $sizeBytes,
            'released_at'  => now(),
            'is_active'    => false,
            'release_notes'=> $this->option('notes') ?? '',
        ]);

        if ($this->option('activate')) {
            $pkg->activate();
            $this->info("Package {$version} registered and activated.");
        } else {
            $this->info("Package {$version} registered (inactive). Run with --activate to make it live.");
        }

        $this->table(
            ['Version', 'SHA-256', 'Size', 'Local Path', 'Active'],
            [[$pkg->version, substr($pkg->sha256, 0, 16) . '...', $pkg->sizeHuman(), $path, $pkg->is_active ? 'YES' : 'no']]
        );

        return 0;
    }
}
