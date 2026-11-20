<?php

namespace Tests\Unit\CustomerDeletion;

use App\Services\CustomerDeletion\Guards\PurgeGuardViolation;
use App\Services\CustomerDeletion\Purge\TenantObjectPurger;
use Aws\CommandInterface;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;

/**
 * ADR-090 — TenantObjectPurger against a mocked S3 API: pagination, the
 * per-key prefix check before ANY delete, DeleteObjects errors, the
 * verified-empty postcondition, and versioned buckets.
 */
class TenantObjectPurgerTest extends TestCase
{
    /** @var list<CommandInterface> */
    private array $commands = [];

    private function purger(MockHandler $mock): TenantObjectPurger
    {
        $client = new S3Client([
            'version'     => 'latest',
            'region'      => 'ir-thr-at1',
            'endpoint'    => 'https://s3.example.test',
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => 'k', 'secret' => 's'],
            'handler'     => $mock,
        ]);

        return new TenantObjectPurger(fn () => ['client' => $client, 'bucket' => 'safhenokhost-backups']);
    }

    private function mock(array $results): MockHandler
    {
        $mock = new MockHandler();
        foreach ($results as $result) {
            $mock->append(function (CommandInterface $cmd) use ($result) {
                $this->commands[] = $cmd;
                return $result instanceof \Closure ? $result($cmd) : $result;
            });
        }

        return $mock;
    }

    private function names(): array
    {
        return array_map(fn (CommandInterface $c) => $c->getName(), $this->commands);
    }

    public function test_paginates_and_deletes_every_object_then_verifies_empty(): void
    {
        $purger = $this->purger($this->mock([
            new Result([]), // GetBucketVersioning: never enabled
            new Result(['Contents' => [['Key' => 'tenants/site-1/db/a.sql.gz', 'Size' => 10]], 'IsTruncated' => true, 'NextContinuationToken' => 't2']),
            new Result(['Contents' => [['Key' => 'tenants/site-1/db/b.sql.gz', 'Size' => 20]], 'IsTruncated' => false]),
            new Result(['Deleted' => [], 'Errors' => []]),
            new Result(['Contents' => [], 'IsTruncated' => false]),
        ]));

        $result = $purger->purge(TenantObjectPurger::TARGET_BACKUPS, 'tenants/site-1/');

        $this->assertSame(2, $result['deleted']);
        $this->assertSame(30, $result['bytes']);
        $this->assertSame('Unversioned', $result['versioning']);
        $this->assertSame(['GetBucketVersioning', 'ListObjectsV2', 'ListObjectsV2', 'DeleteObjects', 'ListObjectsV2'], $this->names());

        $this->assertSame('t2', $this->commands[2]['ContinuationToken']);
        $this->assertSame('tenants/site-1/', $this->commands[1]['Prefix']);
        $this->assertSame(
            [['Key' => 'tenants/site-1/db/a.sql.gz'], ['Key' => 'tenants/site-1/db/b.sql.gz']],
            $this->commands[3]['Delete']['Objects'],
        );
    }

    public function test_a_listed_key_outside_the_prefix_aborts_before_anything_is_deleted(): void
    {
        $purger = $this->purger($this->mock([
            new Result([]),
            new Result(['Contents' => [
                ['Key' => 'tenants/site-1/db/a.sql.gz', 'Size' => 10],
                ['Key' => 'tenants/site-10/db/x.sql.gz', 'Size' => 10], // a misbehaving listing
            ], 'IsTruncated' => false]),
        ]));

        try {
            $purger->purge(TenantObjectPurger::TARGET_BACKUPS, 'tenants/site-1/');
            $this->fail('expected a guard violation');
        } catch (PurgeGuardViolation) {
            $this->assertNotContains('DeleteObjects', $this->names());
        }
    }

    public function test_per_key_delete_errors_fail_the_purge(): void
    {
        $purger = $this->purger($this->mock([
            new Result([]),
            new Result(['Contents' => [['Key' => 'sites/3/media/a.png', 'Size' => 1]], 'IsTruncated' => false]),
            new Result(['Errors' => [['Key' => 'sites/3/media/a.png', 'Code' => 'AccessDenied']]]),
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AccessDenied');
        $purger->purge(TenantObjectPurger::TARGET_MEDIA, 'sites/3/');
    }

    public function test_objects_still_present_after_delete_fail_the_purge(): void
    {
        $purger = $this->purger($this->mock([
            new Result([]),
            new Result(['Contents' => [['Key' => 'sites/3/media/a.png', 'Size' => 1]], 'IsTruncated' => false]),
            new Result(['Errors' => []]),
            new Result(['Contents' => [['Key' => 'sites/3/media/a.png', 'Size' => 1]], 'IsTruncated' => false]),
        ]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('باقی ماند');
        $purger->purge(TenantObjectPurger::TARGET_MEDIA, 'sites/3/');
    }

    public function test_versioned_bucket_deletes_every_version_and_delete_marker(): void
    {
        $purger = $this->purger($this->mock([
            new Result(['Status' => 'Enabled']),
            new Result([
                'Versions'      => [['Key' => 'sites/3/media/a.png', 'VersionId' => 'v1', 'Size' => 5], ['Key' => 'sites/3/media/a.png', 'VersionId' => 'v2', 'Size' => 6]],
                'DeleteMarkers' => [['Key' => 'sites/3/media/b.png', 'VersionId' => 'm1']],
                'IsTruncated'   => false,
            ]),
            new Result(['Errors' => []]),
            new Result(['Versions' => [], 'DeleteMarkers' => [], 'IsTruncated' => false]),
        ]));

        $result = $purger->purge(TenantObjectPurger::TARGET_MEDIA, 'sites/3/');

        $this->assertSame('Enabled', $result['versioning']);
        $this->assertSame(3, $result['deleted']);
        $this->assertSame(['GetBucketVersioning', 'ListObjectVersions', 'DeleteObjects', 'ListObjectVersions'], $this->names());
        $this->assertSame([
            ['Key' => 'sites/3/media/a.png', 'VersionId' => 'v1'],
            ['Key' => 'sites/3/media/a.png', 'VersionId' => 'v2'],
            ['Key' => 'sites/3/media/b.png', 'VersionId' => 'm1'],
        ], $this->commands[2]['Delete']['Objects']);
    }

    public function test_an_undelimited_prefix_is_refused_without_any_request(): void
    {
        $purger = $this->purger($this->mock([]));

        $this->expectException(PurgeGuardViolation::class);
        try {
            $purger->purge(TenantObjectPurger::TARGET_BACKUPS, 'tenants/site-1');
        } finally {
            $this->assertSame([], $this->commands);
        }
    }

    public function test_inventory_counts_objects_and_bytes(): void
    {
        $purger = $this->purger($this->mock([
            new Result([]),
            new Result(['Contents' => [['Key' => 'sites/3/a', 'Size' => 7], ['Key' => 'sites/3/b', 'Size' => 3]], 'IsTruncated' => false]),
        ]));

        $this->assertSame(['objects' => 2, 'bytes' => 10, 'versioning' => 'Unversioned'], $purger->inventory(TenantObjectPurger::TARGET_MEDIA, 'sites/3/'));
    }
}
