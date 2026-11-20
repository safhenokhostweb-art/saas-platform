<?php

namespace Tests\Unit\CustomerDeletion\Guards;

use App\Services\CustomerDeletion\Guards\PurgeGuardViolation;
use App\Services\CustomerDeletion\Guards\RemotePathGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** ADR-090 — the only gate in front of `rm -rf` on a customer server. */
class RemotePathGuardTest extends TestCase
{
    private const SLUG = 'cust_shop_co_42';

    public function test_accepts_exactly_this_installations_vps_directory(): void
    {
        $this->assertSame('/var/www/cust_shop_co_42', RemotePathGuard::assertVpsSiteRoot('/var/www/cust_shop_co_42', self::SLUG));
        $this->assertSame('/var/www/cust_shop_co_42', RemotePathGuard::assertVpsSiteRoot('/var/www/cust_shop_co_42/', self::SLUG));
    }

    public static function unsafeVpsPaths(): array
    {
        return [
            'empty'                    => [''],
            'root'                     => ['/'],
            'web base'                 => ['/var/www'],
            'web base trailing slash'  => ['/var/www/'],
            'default html'             => ['/var/www/html'],
            'relative'                 => ['var/www/cust_shop_co_42'],
            'traversal'                => ['/var/www/cust_shop_co_42/../saas-platform'],
            'dot segment'              => ['/var/www/./cust_shop_co_42'],
            'double slash'             => ['/var/www//cust_shop_co_42'],
            'glob'                     => ['/var/www/cust_*'],
            'space'                    => ['/var/www/cust_shop_co_42 /'],
            'shell metachar'           => ['/var/www/cust_shop_co_42;rm -rf /'],
            'newline'                  => ["/var/www/cust_shop_co_42\n/"],
            'another installation'     => ['/var/www/cust_shop_co_43'],
            'id prefix collision'      => ['/var/www/cust_shop_co_4'],
            'platform tree'            => ['/var/www/saas-platform'],
            'customer-site tree'       => ['/var/www/customer-site'],
            'hub tree'                 => ['/var/www/deployment-system'],
            'subdirectory of the site' => ['/var/www/cust_shop_co_42/storage'],
            'etc'                      => ['/etc'],
            'home'                     => ['/home'],
        ];
    }

    #[DataProvider('unsafeVpsPaths')]
    public function test_rejects_every_path_that_is_not_the_installations_own_directory(string $path): void
    {
        $this->expectException(PurgeGuardViolation::class);
        RemotePathGuard::assertVpsSiteRoot($path, self::SLUG);
    }

    public function test_rejects_an_invalid_slug_even_when_the_path_matches_it(): void
    {
        $this->expectException(PurgeGuardViolation::class);
        RemotePathGuard::assertVpsSiteRoot('/var/www/html', 'html');
    }

    public function test_shared_hosting_accepts_a_site_directory_inside_the_account(): void
    {
        $this->assertSame('/home/johndoe/shop.example.com', RemotePathGuard::assertSharedHostingSiteRoot('/home/johndoe/shop.example.com', 'johndoe'));
        $this->assertSame(
            '/home/johndoe/domains/addon.example.com/public_html',
            RemotePathGuard::assertSharedHostingSiteRoot('/home/johndoe/domains/addon.example.com/public_html', 'johndoe'),
        );
        $this->assertSame('/home/johndoe/public_html/shop', RemotePathGuard::assertSharedHostingSiteRoot('/home/johndoe/public_html/shop', 'johndoe'));
    }

    public static function unsafeSharedHostingPaths(): array
    {
        return [
            'account root'        => ['/home/johndoe'],
            'public_html'         => ['/home/johndoe/public_html'],
            'domains'             => ['/home/johndoe/domains'],
            'mail'                => ['/home/johndoe/mail'],
            'dotdir'              => ['/home/johndoe/.ssh'],
            'other account'       => ['/home/janedoe/shop.example.com'],
            'username prefix'     => ['/home/johndoe2/shop.example.com'],
            'outside home'        => ['/var/www/shop'],
            'traversal'           => ['/home/johndoe/shop/../../janedoe'],
        ];
    }

    #[DataProvider('unsafeSharedHostingPaths')]
    public function test_shared_hosting_rejects_account_level_or_foreign_paths(string $path): void
    {
        $this->expectException(PurgeGuardViolation::class);
        RemotePathGuard::assertSharedHostingSiteRoot($path, 'johndoe');
    }

    public function test_vhost_path_must_be_the_slug_file_in_the_given_directory(): void
    {
        $this->assertSame(
            '/etc/nginx/sites-available/' . self::SLUG,
            RemotePathGuard::assertVhostPath('/etc/nginx/sites-available/' . self::SLUG, self::SLUG, '/etc/nginx/sites-available'),
        );

        $this->expectException(PurgeGuardViolation::class);
        RemotePathGuard::assertVhostPath('/etc/nginx/sites-available/default', self::SLUG, '/etc/nginx/sites-available');
    }
}
