<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateMasterKeyCommand extends Command
{
    protected $signature = 'installer:generate-master-key {--show : Print key without writing to .env}';
    protected $description = 'Generate a new INSTALLER_MASTER_KEY for CredentialVault encryption';

    public function handle(): int
    {
        $key = base64_encode(random_bytes(32));

        if ($this->option('show')) {
            $this->line($key);
            return 0;
        }

        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            $this->error('.env file not found');
            return 1;
        }

        $content = file_get_contents($envPath);
        $line = "INSTALLER_MASTER_KEY=\"{$key}\"";

        if (str_contains($content, 'INSTALLER_MASTER_KEY=')) {
            $this->warn('INSTALLER_MASTER_KEY already exists in .env — NOT overwriting.');
            $this->info('Run with --show to view a new key, then update manually.');
            return 1;
        }

        file_put_contents($envPath, $content . PHP_EOL . $line . PHP_EOL);

        $this->info('INSTALLER_MASTER_KEY added to .env');
        $this->comment('Key: ' . substr($key, 0, 8) . '...' . substr($key, -4) . ' (partial shown for confirmation)');
        $this->warn('Back this up securely — loss of this key means loss of all stored SSH credentials.');

        return 0;
    }
}
