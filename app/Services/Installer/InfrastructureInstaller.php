<?php
namespace App\Services\Installer;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Handles platform installation, seeding, cache management, and infrastructure config generation.
 */
class InfrastructureInstaller
{
    // ─── Migration & Seeding ─────────────────────────────────────────────────

    public function runMigrations(bool $force = true): array
    {
        try {
            $exitCode = Artisan::call('migrate', ['--force' => $force]);
            $output   = Artisan::output();
            return [
                'success' => $exitCode === 0,
                'output'  => $output,
                'message' => $exitCode === 0 ? 'Migration با موفقیت اجرا شد' : 'Migration با خطا روبرو شد',
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'output' => '', 'message' => 'خطا در migration: ' . $e->getMessage()];
        }
    }

    public function runSeeder(string $seederClass = 'Database\\Seeders\\PlatformSeeder'): array
    {
        try {
            $exitCode = Artisan::call('db:seed', ['--class' => $seederClass, '--force' => true]);
            $output   = Artisan::output();
            return [
                'success' => $exitCode === 0,
                'output'  => $output,
                'message' => $exitCode === 0
                    ? "Seeder {$seederClass} با موفقیت اجرا شد"
                    : "Seeder ناموفق: {$output}",
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'output' => '', 'message' => 'خطا در seeder: ' . $e->getMessage()];
        }
    }

    public function createSuperAdmin(array $data): array
    {
        try {
            $user = \App\Models\User::updateOrCreate(
                ['mobile' => $data['mobile']],
                [
                    'name'     => $data['name'],
                    'password' => Hash::make($data['password']),
                    'is_admin' => 1,
                ]
            );
            return [
                'success' => true,
                'user_id' => $user->id,
                'message' => "Super Admin ایجاد/بروزرسانی شد (ID: {$user->id})",
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'خطا در ایجاد Super Admin: ' . $e->getMessage()];
        }
    }

    public function createPlatformSettings(array $settings = []): array
    {
        try {
            $defaults = array_merge([
                'platform_name'    => 'سکوی SaaS',
                'platform_domain'  => config('app.url'),
                'platform_version' => '1.0.0',
                'installed_at'     => now()->toISOString(),
            ], $settings);

            foreach ($defaults as $key => $value) {
                DB::table('settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => $value, 'updated_at' => now()]
                );
            }

            return ['success' => true, 'message' => 'تنظیمات پلتفرم ثبت شد'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'خطا در ثبت تنظیمات: ' . $e->getMessage()];
        }
    }

    // ─── Cache & Optimize ────────────────────────────────────────────────────

    public function clearCacheAndOptimize(): array
    {
        $steps = [
            ['config:clear', 'پاک‌سازی کش config'],
            ['cache:clear',  'پاک‌سازی کش اپلیکیشن'],
            ['view:clear',   'پاک‌سازی کش view'],
            ['route:clear',  'پاک‌سازی کش route'],
            ['config:cache', 'کش config'],
            ['route:cache',  'کش route'],
            ['view:cache',   'کش view'],
        ];

        $results = [];
        foreach ($steps as [$cmd, $label]) {
            try {
                Artisan::call($cmd);
                $results[] = ['command' => $cmd, 'label' => $label, 'success' => true];
            } catch (\Throwable $e) {
                $results[] = [
                    'command' => $cmd,
                    'label'   => $label,
                    'success' => false,
                    'error'   => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => collect($results)->every(fn($r) => $r['success']),
            'results' => $results,
            'message' => 'کش پاک‌سازی و بهینه‌سازی انجام شد',
        ];
    }

    public function enableMaintenanceMode(): void
    {
        try {
            Artisan::call('down');
        } catch (\Throwable) {}
    }

    public function disableMaintenanceMode(): void
    {
        try {
            Artisan::call('up');
        } catch (\Throwable) {}
    }

    // ─── Config Generators ───────────────────────────────────────────────────

    public function generateSupervisorConfig(): array
    {
        $basePath = base_path();
        $phpBin   = PHP_BINARY ?: '/usr/bin/php';

        $config = <<<CONF
[program:saas-platform-worker]
process_name=%(program_name)s_%(process_num)02d
command={$phpBin} {$basePath}/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/saas-platform-worker.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
stopwaitsecs=3600

[program:saas-platform-restore-worker]
process_name=%(program_name)s_%(process_num)02d
command={$phpBin} {$basePath}/artisan queue:work redis --queue=restore --sleep=3 --tries=1 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/saas-platform-restore-worker.log
stopwaitsecs=3600
CONF;

        return [
            'success'   => true,
            'config'    => $config,
            'file_path' => '/etc/supervisor/conf.d/saas-platform.conf',
            'message'   => 'پیکربندی Supervisor آماده است',
        ];
    }

    public function generateReverbConfig(): array
    {
        $basePath = base_path();
        $phpBin   = PHP_BINARY ?: '/usr/bin/php';
        $host     = config('reverb.servers.reverb.host', '0.0.0.0');
        $port     = config('reverb.servers.reverb.port', 8080);

        $config = <<<CONF
[program:saas-platform-reverb]
process_name=%(program_name)s
command={$phpBin} {$basePath}/artisan reverb:start --host={$host} --port={$port}
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/saas-platform-reverb.log
stopwaitsecs=10
CONF;

        return [
            'success'   => true,
            'config'    => $config,
            'file_path' => '/etc/supervisor/conf.d/saas-platform-reverb.conf',
            'message'   => "پیکربندی Reverb برای {$host}:{$port} آماده است",
        ];
    }

    public function generateCronLine(): string
    {
        return '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1';
    }
}
