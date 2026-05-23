<?php

namespace SaaSPlatform\Modules\Forms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class InstallFormsModule extends Command
{
    protected $signature = 'forms:install
        {--force : Force overwrite existing files}
        {--no-migrate : Skip running migrations}';

    protected $description = 'Install the Forms module into the application';

    public function handle(): int
    {
        $this->info('نصب ماژول فرم‌ها...');

        // Publish config
        $this->call('vendor:publish', [
            '--provider' => 'SaaSPlatform\\Modules\\Forms\\Providers\\FormsServiceProvider',
            '--tag' => 'forms-config',
            '--force' => $this->option('force'),
        ]);

        // Publish views
        $this->call('vendor:publish', [
            '--provider' => 'SaaSPlatform\\Modules\\Forms\\Providers\\FormsServiceProvider',
            '--tag' => 'forms-views',
            '--force' => $this->option('force'),
        ]);

        // Publish and run migrations
        if (!$this->option('no-migrate')) {
            $this->call('vendor:publish', [
                '--provider' => 'SaaSPlatform\\Modules\\Forms\\Providers\\FormsServiceProvider',
                '--tag' => 'forms-migrations',
                '--force' => $this->option('force'),
            ]);

            $this->call('migrate');
        }

        // Clear cache
        $this->call('optimize:clear');

        $this->info('✅ ماژول فرم‌ها با موفقیت نصب شد.');

        return self::SUCCESS;
    }
}
