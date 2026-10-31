<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\Site;
use App\Services\CustomerInstallation\Drivers\FakeRemoteDriver;
use App\Services\DevOps\PosixShell;
use App\Services\DevOps\TenantTarget;
use App\Services\DevOps\TenantTargetResolver;

/**
 * Simulates a tenant's host for the DevOps governor/pruner: a FakeRemoteDriver
 * answering the realpath probes, `find` listings, and du/df the services send.
 * The fake ignores `-mmin`, so the PHP-side retention filter is what's tested.
 */
trait FakesTenantHost
{
    protected function makeDevOpsSite(string $title = 'فروشگاه آزمایشی'): Site
    {
        $customer = Customer::create(['name' => 'مشتری تست', 'mobile' => '0912' . random_int(1000000, 9999999)]);

        return Site::create([
            'customer_id' => $customer->id,
            'title'       => $title,
            'domain'      => 'devops-' . uniqid() . '.example.com',
            'is_active'   => true,
        ]);
    }

    /**
     * @param array<string, array<string, array{0:int,1:int}>> $dirs
     *        storage-relative dir => [relative file path => [size bytes, age seconds]]
     * @param array<string, string> $symlinkedDirs storage-relative dir => the real path the host reports
     */
    protected function fakeTenantHost(
        string $root,
        array $dirs = [],
        bool $laravel = true,
        array $symlinkedDirs = [],
        array $extraRecords = [],
    ): FakeRemoteDriver {
        $driver = new FakeRemoteDriver();
        $q = fn (string $p) => PosixShell::quote($p);

        $driver->addResponse(
            'test -f ',
            $laravel ? 0 : 1,
            $laravel ? "{$root}\nLARAVEL_OK\n" : "{$root}\n"
        );

        foreach ($dirs as $relative => $files) {
            $dir = "{$root}/{$relative}";
            $driver->addResponse('test -d ' . $q($dir) . ' &&', 0, ($symlinkedDirs[$relative] ?? $dir) . "\n");

            $records = '';
            foreach ($files as $file => [$size, $age]) {
                $mtime = sprintf('%.10f', time() - $age);
                $records .= "{$mtime}\t{$size}\t{$dir}/{$file}\0";
            }
            foreach ($extraRecords[$relative] ?? [] as $raw) {
                $records .= $raw . "\0";
            }
            $driver->addResponse('find ' . $q($dir) . ' ', 0, $records);
        }

        // Directories not listed above do not exist on the host.
        $driver->addResponse('test -d ', 1, '');

        return $driver;
    }

    protected function bindTenantTarget(Site $site, FakeRemoteDriver $driver, string $root, ?int $serverId = null): void
    {
        $resolver = $this->createMock(TenantTargetResolver::class);
        $resolver->method('resolve')->willReturnCallback(
            fn (Site $s) => new TenantTarget($s, $driver, $root, null, $serverId)
        );

        $this->app->instance(TenantTargetResolver::class, $resolver);
    }

    /** @return string[] rm commands the driver received */
    protected function rmCommands(FakeRemoteDriver $driver): array
    {
        return array_values(array_filter($driver->getCommandLog(), fn ($c) => str_starts_with($c, 'rm ')));
    }
}
