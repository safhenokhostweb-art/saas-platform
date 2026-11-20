<?php

namespace Tests\Unit\CustomerDeletion\Guards;

use App\Services\CustomerDeletion\Guards\ObjectPrefixGuard;
use App\Services\CustomerDeletion\Guards\PurgeGuardViolation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** ADR-090 — Arvan prefixes a purge may delete. */
class ObjectPrefixGuardTest extends TestCase
{
    public function test_backup_prefixes_are_exact_and_delimited(): void
    {
        $this->assertSame([
            'tenants/site-1/',
            'customers/site-1/',
            'customer-sites/tenant-1/',
            'saas-platform/archives/site_1_',
        ], ObjectPrefixGuard::backupPrefixes(1));
    }

    public function test_media_prefix_is_the_site_directory_with_trailing_slash(): void
    {
        $this->assertSame(['sites/1/'], ObjectPrefixGuard::mediaPrefixes(1));
    }

    public function test_site_1_prefixes_never_match_site_10_objects(): void
    {
        $site10Keys = [
            'tenants/site-10/db/2026.sql.gz',
            'customers/site-10/baselines/x.sql.gz',
            'customer-sites/tenant-10/database/x.sql.gz',
            'saas-platform/archives/site_10_20261120_101010.zip',
            'sites/10/media/logo.png',
        ];

        foreach (array_merge(ObjectPrefixGuard::backupPrefixes(1), ObjectPrefixGuard::mediaPrefixes(1)) as $prefix) {
            foreach ($site10Keys as $key) {
                $this->assertFalse(str_starts_with($key, $prefix), "{$prefix} must not match {$key}");
            }
        }
    }

    public static function invalidIds(): array
    {
        return ['zero' => [0], 'negative' => [-3]];
    }

    #[DataProvider('invalidIds')]
    public function test_rejects_non_positive_site_ids(int $id): void
    {
        $this->expectException(PurgeGuardViolation::class);
        ObjectPrefixGuard::backupPrefixes($id);
    }

    public static function foreignPrefixes(): array
    {
        return [
            'empty (whole bucket)'   => [''],
            'no trailing slash'      => ['tenants/site-1'],
            'other site'             => ['tenants/site-2/'],
            'parent'                 => ['tenants/'],
            'platform db backups'    => ['saas-platform/database/'],
            'wildcard-looking'       => ['tenants/site-*/'],
            'media prefix as backup' => ['sites/1/'],
        ];
    }

    #[DataProvider('foreignPrefixes')]
    public function test_backup_prefix_must_be_one_of_the_sites_own(string $prefix): void
    {
        $this->expectException(PurgeGuardViolation::class);
        ObjectPrefixGuard::assertBackupPrefix($prefix, 1);
    }

    public function test_media_prefix_must_be_the_sites_own(): void
    {
        $this->assertSame('sites/7/', ObjectPrefixGuard::assertMediaPrefix('sites/7/', 7));

        $this->expectException(PurgeGuardViolation::class);
        ObjectPrefixGuard::assertMediaPrefix('sites/', 7);
    }

    public function test_key_must_sit_inside_its_prefix(): void
    {
        ObjectPrefixGuard::assertKeyWithin('sites/7/media/a.png', 'sites/7/');
        $this->addToAssertionCount(1);

        foreach (['sites/70/media/a.png', 'other/sites/7/a.png', 'sites/7/../8/a.png'] as $key) {
            try {
                ObjectPrefixGuard::assertKeyWithin($key, 'sites/7/');
                $this->fail("{$key} should have been rejected");
            } catch (PurgeGuardViolation) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
