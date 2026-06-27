<?php

namespace App\Console\Commands;

use App\Models\CustomerInstallationPackage;
use Illuminate\Console\Command;

class RegisterInstallPackageCommand extends Command
{
    protected $signature = 'customer-install:register-package
                            {version : Semver e.g. 1.0.0}
                            {arvan-key : Path in Arvan storage e.g. saas-platform/releases/customer-site/v1.0.0/customer-site-v1.0.0.zip}
                            {sha256 : SHA-256 checksum of the ZIP}
                            {size-bytes : File size in bytes}
                            {--activate : Mark as active immediately}
                            {--notes= : Release notes}';

    protected $description = 'Register a customer-site release package in the database';

    public function handle(): int
    {
        $version = $this->argument('version');
        $arvanKey = $this->argument('arvan-key');
        $sha256 = $this->argument('sha256');
        $sizeBytes = (int) $this->argument('size-bytes');

        if (strlen($sha256) !== 64) {
            $this->error("SHA-256 must be 64 hex characters. Got: " . strlen($sha256));
            return 1;
        }

        $existing = CustomerInstallationPackage::where('version', $version)->first();
        if ($existing) {
            $this->warn("Package version {$version} already registered.");
            return 1;
        }

        $pkg = CustomerInstallationPackage::create([
            'version'      => $version,
            'arvan_key'    => $arvanKey,
            'sha256'       => strtolower($sha256),
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

        $this->table(['Version', 'SHA-256', 'Size', 'Active'], [
            [$pkg->version, substr($pkg->sha256, 0, 16) . '...', $pkg->sizeHuman(), $pkg->is_active ? 'YES' : 'no'],
        ]);

        return 0;
    }
}
