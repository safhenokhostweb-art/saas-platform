<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Infrastructure\InfraServer;
use App\Models\Infrastructure\InfraDomain;
use App\Models\Infrastructure\InfraHosting;
use App\Models\Infrastructure\InfraDatabaseServer;
use App\Models\Infrastructure\InfraStorageProvider;

/**
 * Seeds the infrastructure module with real production server data.
 * Server: 87.107.12.81:9011 (FastCreate VPS, Ubuntu 24.04.4 LTS)
 * Collected: 2026-06-15
 */
class InfrastructureProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('ثبت اطلاعات زیرساخت پروداکشن...');

        DB::transaction(function () {
            $server = $this->seedServer();
            $this->seedDomains($server);
            $this->seedHosting($server);
            $this->seedDatabases($server);
            $this->seedStorage($server);
        });

        $this->command->info('✅ اطلاعات زیرساخت با موفقیت ثبت شد.');
    }

    private function seedServer(): InfraServer
    {
        $this->command->line('  → سرور اصلی پروداکشن...');

        return InfraServer::updateOrCreate(
            ['ip_address' => '87.107.12.81'],
            [
                'name'               => 'سرور اصلی پروداکشن',
                'hostname'           => 'fastcreate-010099',
                'ip_address'         => '87.107.12.81',
                'ipv6_address'       => null,
                'environment'        => 'production',
                'roles'              => ['application', 'database', 'cache', 'queue', 'storage'],
                'provider'           => 'FastCreate',
                'datacenter'         => 'ایران',
                'cpu_cores'          => 1,
                'ram_gb'             => 2,
                'disk_gb'            => 20,
                'monthly_cost'       => null,
                'currency'           => 'IRR',
                'os'                 => 'Ubuntu 24.04.4 LTS (kernel 6.8.0-31-generic)',
                'status'             => 'online',
                'last_checked_at'    => now(),
                'uptime_percent'     => 99,
                'last_backup_at'     => null,
                'last_deployment_at' => now(),
                'is_active'          => true,
                'notes'              => implode("\n", [
                    'SSH Port: 9011 | KVM (Red Hat QEMU) | x86_64',
                    'CPU: Intel Broadwell @ 2.0GHz | RAM: 1.9 GiB | Disk: 20 GB (41% پر)',
                    'PHP 8.3.6 FPM | MySQL 8.0.45 | Redis 7.0.15 | Nginx 1.24.0 | Node 20.19',
                    'PHP limits: memory=256M | upload=20M | post=25M | max_exec=120s',
                    'Redis: maxmemory=200MB allkeys-lru',
                    'HTTPS: self-signed wildcard *.safhenokhost.ir (تا 2027-06-16)',
                    'Supervisor: 2x saas-platform-worker (redis) + 1x customer-site-worker (database)',
                    'Scheduler: /etc/cron.d/laravel-scheduler (هر دقیقه)',
                    '',
                    '✅ رفع‌شده (2026-06-15/16):',
                    '  APP_DEBUG=false هر دو | SESSION_DRIVER=redis در saas-platform',
                    '  APP_URL=https در هر دو | Supervisor workers راه‌اندازی | HTTPS فعال',
                    '',
                    '⚠ باز: SSL واقعی (ArvanCloud CDN) | PHP 8.4 (sury.org block از ایران)',
                ]),
            ]
        );
    }

    private function seedDomains(InfraServer $server): void
    {
        $this->command->line('  → دامنه‌ها...');

        // Main customer-site domain
        InfraDomain::updateOrCreate(
            ['domain' => 'safhenokhost.ir'],
            [
                'type'                => 'tenant',
                'server_id'           => $server->id,
                'site_id'             => null,
                'registrar'           => null,
                'registered_at'       => null,
                'expires_at'          => null,
                'ssl_enabled'         => true,
                'ssl_provider'        => 'Self-Signed',
                'ssl_expires_at'      => '2027-06-16',
                'ssl_status'          => 'valid',
                'dns_provider'        => 'ArvanCloud',
                'dns_notes'           => 'دامنه اصلی سایت مشتری (customer-site). A record → 87.107.12.81 | SSL واقعی: ArvanCloud CDN فعال کنید',
                'status'              => 'active',
                'redirects_to_https'  => true,
                'notes'               => 'Nginx: /etc/nginx/sites-enabled/customer-site | HTTP→HTTPS redirect | root: /var/www/customer-site/public | PHP-FPM 8.3',
            ]
        );

        // www redirect
        InfraDomain::updateOrCreate(
            ['domain' => 'www.safhenokhost.ir'],
            [
                'type'                => 'tenant',
                'server_id'           => $server->id,
                'site_id'             => null,
                'registrar'           => null,
                'ssl_enabled'         => true,
                'ssl_provider'        => 'Self-Signed',
                'ssl_expires_at'      => '2027-06-16',
                'ssl_status'          => 'valid',
                'dns_provider'        => 'ArvanCloud',
                'dns_notes'           => 'www redirect به safhenokhost.ir (همان vhost)',
                'status'              => 'active',
                'redirects_to_https'  => true,
                'notes'               => 'هر دو دامنه در یک nginx server block تعریف شده‌اند | HTTP→HTTPS redirect فعال',
            ]
        );

        // Platform admin panel
        InfraDomain::updateOrCreate(
            ['domain' => 'panel.safhenokhost.ir'],
            [
                'type'                => 'platform',
                'server_id'           => $server->id,
                'site_id'             => null,
                'registrar'           => null,
                'ssl_enabled'         => true,
                'ssl_provider'        => 'Self-Signed',
                'ssl_expires_at'      => '2027-06-16',
                'ssl_status'          => 'valid',
                'dns_provider'        => 'ArvanCloud',
                'dns_notes'           => 'دامنه پنل مدیریت پلتفرم (saas-platform). A record → 87.107.12.81',
                'status'              => 'active',
                'redirects_to_https'  => true,
                'notes'               => 'APP_URL=https://panel.safhenokhost.ir | Nginx: /etc/nginx/sites-enabled/saas-platform | HTTP→HTTPS redirect | root: /var/www/saas-platform/public',
            ]
        );
    }

    private function seedHosting(InfraServer $server): void
    {
        $this->command->line('  → هاستینگ...');

        InfraHosting::updateOrCreate(
            ['name' => 'FastCreate VPS پروداکشن'],
            [
                'server_id'               => $server->id,
                'provider'                => 'FastCreate',
                'plan'                    => 'VPS استاندارد (1 vCore / 2GB RAM / 20GB SSD)',
                'account_email'           => null,
                'panel_url'               => null,
                'environment'             => 'production',
                'cpu_cores_allocated'     => 1,
                'ram_gb_allocated'        => 2,
                'disk_gb_allocated'       => 20,
                'bandwidth_gb_allocated'  => null,
                'monthly_cost'            => null,
                'currency'                => 'IRR',
                'billing_cycle_start'     => null,
                'billing_cycle_end'       => null,
                'next_renewal_at'         => null,
                'status'                  => 'active',
                'notes'                   => implode("\n", [
                    'VPS ایرانی — KVM virtualization',
                    'دو پروژه روی یک سرور:',
                    '  1. saas-platform → /var/www/saas-platform (2.9 GB)',
                    '  2. customer-site → /var/www/customer-site (251 MB)',
                    'Deploy از طریق SCP + SSH (deploy.ps1 / deploy.sh)',
                    'هر دو پروژه از یک PHP-FPM socket استفاده می‌کنند',
                ]),
            ]
        );
    }

    private function seedDatabases(InfraServer $server): void
    {
        $this->command->line('  → دیتابیس‌ها...');

        // MySQL
        InfraDatabaseServer::updateOrCreate(
            ['name' => 'MySQL اصلی پروداکشن'],
            [
                'server_id'           => $server->id,
                'engine'              => 'mysql',
                'version'             => '8.0.45',
                'host'                => 'localhost',
                'port'                => 3306,
                'environment'         => 'production',
                'size_mb'             => 275,
                'size_mb_prev_month'  => null,
                'active_connections'  => null,
                'max_connections'     => 151,
                'status'              => 'online',
                'last_backup_at'      => null,
                'backup_storage'      => null,
                'backup_frequency'    => 'daily',
                'last_checked_at'     => now(),
                'replication_enabled' => false,
                'replication_role'    => 'primary',
                'is_active'           => true,
                'notes'               => implode("\n", [
                    'پایگاه داده‌ها:',
                    '  - saas_platform (اصلی پلتفرم) — user: saas_user',
                    '  - customer_site (سایت مشتری) — user: saas_user',
                    'MySQL 8.0.45-0ubuntu0.24.04.1',
                    'Auth: root بدون رمز (socket auth)',
                    '⚠ بکاپ اتوماتیک تنظیم نشده است',
                ]),
            ]
        );

        // Redis
        InfraDatabaseServer::updateOrCreate(
            ['name' => 'Redis اصلی پروداکشن'],
            [
                'server_id'           => $server->id,
                'engine'              => 'redis',
                'version'             => '7.0.15',
                'host'                => '127.0.0.1',
                'port'                => 6379,
                'environment'         => 'production',
                'size_mb'             => 2,
                'size_mb_prev_month'  => null,
                'active_connections'  => 1,
                'max_connections'     => null,
                'status'              => 'online',
                'last_backup_at'      => null,
                'backup_storage'      => null,
                'backup_frequency'    => null,
                'last_checked_at'     => now(),
                'replication_enabled' => false,
                'replication_role'    => 'primary',
                'is_active'           => true,
                'notes'               => implode("\n", [
                    'Redis 7.0.15 — uptime: 6 روز',
                    'Memory استفاده‌شده: 1.22 MB',
                    'maxmemory: تنظیم نشده (⚠ خطر: OOM در peak load)',
                    'saas-platform: CACHE_DRIVER=redis, QUEUE_CONNECTION=redis',
                    'customer-site: CACHE_STORE=database (از Redis استفاده نمی‌کند)',
                    'DB Map:',
                    '  DB0=queues, DB1=legacy (8 key), DB2=sessions',
                    '  DB3=rate_limit, DB4=users, DB5=customers',
                    '  DB6=themes, DB7=tickets, DB8=modules',
                    'Client: phpredis (igbinary serializer)',
                ]),
            ]
        );
    }

    private function seedStorage(InfraServer $server): void
    {
        $this->command->line('  → استوریج...');

        // saas-platform local storage
        InfraStorageProvider::updateOrCreate(
            ['name' => 'استوریج لوکال — saas-platform'],
            [
                'driver'            => 'local',
                'endpoint'          => null,
                'bucket'            => null,
                'region'            => null,
                'disk_name'         => 'local',
                'purpose'           => 'primary',
                'capacity_gb'       => 20,
                'used_gb'           => 3,
                'used_gb_prev_month'=> null,
                'monthly_cost'      => null,
                'currency'          => 'IRR',
                'status'            => 'active',
                'last_measured_at'  => now(),
                'is_active'         => true,
                'notes'             => implode("\n", [
                    'مسیر: /var/www/saas-platform',
                    'حجم کل دایرکتوری: 2.9 GB (شامل vendor)',
                    'دیسک کل سرور: 20 GB | استفاده: 7.5 GB | آزاد: 12 GB (59%)',
                    'فایل‌سیستم: /dev/vda1 (Linux VirtIO)',
                ]),
            ]
        );

        // customer-site local storage
        InfraStorageProvider::updateOrCreate(
            ['name' => 'استوریج لوکال — customer-site'],
            [
                'driver'            => 'local',
                'endpoint'          => null,
                'bucket'            => null,
                'region'            => null,
                'disk_name'         => 'local',
                'purpose'           => 'tenant-uploads',
                'capacity_gb'       => 20,
                'used_gb'           => 1,
                'used_gb_prev_month'=> null,
                'monthly_cost'      => null,
                'currency'          => 'IRR',
                'status'            => 'active',
                'last_measured_at'  => now(),
                'is_active'         => true,
                'notes'             => implode("\n", [
                    'مسیر: /var/www/customer-site',
                    'حجم کل دایرکتوری: 251 MB',
                    'FILESYSTEM_DISK=local',
                    'آپلود تصاویر مشتری در storage/app/public',
                    '⚠ محدودیت آپلود PHP: 2M (نیاز به افزایش)',
                ]),
            ]
        );
    }
}
