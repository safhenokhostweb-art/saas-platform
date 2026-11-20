<?php

namespace App\Services\CustomerDeletion\Purge;

use App\Services\CustomerDeletion\Guards\ObjectPrefixGuard;
use App\Services\CustomerDeletion\Guards\PurgeGuardViolation;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;

/**
 * ADR-090 — deletes every object under one already-guarded prefix in the
 * private backup bucket or the tenant media bucket, and proves it is gone.
 *
 * - Talks to S3 directly (not Flysystem deleteDirectory) so it can paginate,
 *   check each key against the prefix before deleting it, see DeleteObjects
 *   per-key errors, and handle versioned buckets.
 * - Versioning: neither Arvan bucket had it enabled when this was written
 *   (GetBucketVersioning: never enabled, 2026-11-20). If it is ever enabled or
 *   suspended, a plain DeleteObject only adds a delete marker and keeps the
 *   bytes, so every version and delete marker is deleted by VersionId.
 * - Backups use the platform's own arvan_backup disk client; media uses the
 *   dedicated tenant-media key (config resource_governance.tenant_media) at
 *   the bucket root, never the backup key.
 */
class TenantObjectPurger
{
    public const TARGET_BACKUPS = 'backups';
    public const TARGET_MEDIA   = 'media';

    private const BATCH = 1000;

    /** @var (\Closure(string): array{client: S3Client, bucket: string})|null */
    private ?\Closure $resolver;

    public function __construct(?\Closure $resolver = null)
    {
        $this->resolver = $resolver;
    }

    /**
     * @return array{objects: int, bytes: int, versioning: string}
     */
    public function inventory(string $target, string $prefix): array
    {
        [$client, $bucket] = $this->target($target);
        $versioning = $this->versioning($client, $bucket);

        $objects = 0;
        $bytes   = 0;
        foreach ($this->entries($client, $bucket, $prefix, $versioning) as $entry) {
            $objects++;
            $bytes += $entry['size'];
        }

        return ['objects' => $objects, 'bytes' => $bytes, 'versioning' => $versioning];
    }

    /**
     * @return array{prefix: string, deleted: int, bytes: int, versioning: string}
     */
    public function purge(string $target, string $prefix): array
    {
        if ($prefix === '' || !in_array(substr($prefix, -1), ['/', '_'], true)) {
            throw new PurgeGuardViolation("پیشوند «{$prefix}» باید به / یا _ ختم شود.");
        }

        [$client, $bucket] = $this->target($target);
        $versioning = $this->versioning($client, $bucket);

        $deleted = 0;
        $bytes   = 0;
        $batch   = [];

        // Every key is checked before anything is deleted, so one stray key
        // aborts the whole prefix with nothing removed. Collect first, then delete: deleting while paginating a listing can
        // shift continuation tokens on some S3 implementations.
        $all = [];
        foreach ($this->entries($client, $bucket, $prefix, $versioning) as $entry) {
            ObjectPrefixGuard::assertKeyWithin($entry['key'], $prefix);
            $all[] = $entry;
        }

        foreach ($all as $entry) {
            $object = ['Key' => $entry['key']];
            if ($entry['version'] !== null) {
                $object['VersionId'] = $entry['version'];
            }
            $batch[] = $object;
            $bytes  += $entry['size'];

            if (count($batch) === self::BATCH) {
                $deleted += $this->deleteBatch($client, $bucket, $batch);
                $batch = [];
            }
        }
        if ($batch !== []) {
            $deleted += $this->deleteBatch($client, $bucket, $batch);
        }

        $remaining = iterator_count($this->entries($client, $bucket, $prefix, $versioning));
        if ($remaining > 0) {
            throw new \RuntimeException("پس از حذف، {$remaining} آبجکت زیر «{$prefix}» باقی ماند.");
        }

        return ['prefix' => $prefix, 'deleted' => $deleted, 'bytes' => $bytes, 'versioning' => $versioning];
    }

    /** @return array{0: S3Client, 1: string} */
    private function target(string $target): array
    {
        $resolved = $this->resolver
            ? ($this->resolver)($target)
            : $this->defaultTarget($target);

        return [$resolved['client'], $resolved['bucket']];
    }

    /** @return array{client: S3Client, bucket: string} */
    private function defaultTarget(string $target): array
    {
        // The local .env carries the real Arvan keys and phpunit.xml does not
        // override them: a test that forgot to bind a fake must fail loudly,
        // never delete real objects.
        if (app()->environment('testing')) {
            throw new \RuntimeException('TenantObjectPurger: real object storage is disabled under APP_ENV=testing; bind a fake.');
        }

        if ($target === self::TARGET_BACKUPS) {
            $bucket = (string) config('filesystems.disks.arvan_backup.bucket');
            if ($bucket === '' || (string) config('filesystems.disks.arvan_backup.key') === '') {
                throw new \RuntimeException('تنظیمات باکت پشتیبان آروان (arvan_backup) کامل نیست.');
            }

            /** @var \Illuminate\Filesystem\AwsS3V3Adapter $disk */
            $disk = Storage::disk('arvan_backup');

            return ['client' => $disk->getClient(), 'bucket' => $bucket];
        }

        if ($target === self::TARGET_MEDIA) {
            $cfg = (array) config('resource_governance.tenant_media', []);
            foreach (['access_key', 'secret_key', 'bucket', 'endpoint'] as $required) {
                if (trim((string) ($cfg[$required] ?? '')) === '') {
                    throw new \RuntimeException('تنظیمات باکت مدیا (TENANT_MEDIA_*) کامل نیست؛ مدیا حذف نشد.');
                }
            }

            /** @var \Illuminate\Filesystem\AwsS3V3Adapter $disk */
            $disk = Storage::build([
                'driver'                  => 's3',
                'key'                     => $cfg['access_key'],
                'secret'                  => $cfg['secret_key'],
                'region'                  => $cfg['region'] ?? 'ir-central1',
                'bucket'                  => $cfg['bucket'],
                'endpoint'                => $cfg['endpoint'],
                'use_path_style_endpoint' => true,
                'throw'                   => true,
            ]);

            return ['client' => $disk->getClient(), 'bucket' => (string) $cfg['bucket']];
        }

        throw new \InvalidArgumentException("Unknown purge target: {$target}");
    }

    /** 'Enabled' | 'Suspended' | 'Unversioned' | 'Unknown' (status not readable with this key). */
    private function versioning(S3Client $client, string $bucket): string
    {
        try {
            $status = $client->getBucketVersioning(['Bucket' => $bucket])['Status'] ?? null;
        } catch (\Throwable) {
            return 'Unknown';
        }

        return in_array($status, ['Enabled', 'Suspended'], true) ? $status : 'Unversioned';
    }

    /**
     * Every object (or, on a versioned/unknown bucket, every version and
     * delete marker) under $prefix.
     *
     * @return \Generator<array{key: string, version: ?string, size: int}>
     */
    private function entries(S3Client $client, string $bucket, string $prefix, string $versioning): \Generator
    {
        // 'Unknown' (the key may not read the versioning status, and then
        // usually cannot list versions either) uses the plain listing; the
        // stage output carries the status so the operator can see it.
        if ($versioning === 'Unversioned' || $versioning === 'Unknown') {
            $token = null;
            do {
                $page = $client->listObjectsV2(array_filter([
                    'Bucket'            => $bucket,
                    'Prefix'            => $prefix,
                    'ContinuationToken' => $token,
                ]));
                foreach ($page['Contents'] ?? [] as $object) {
                    yield ['key' => (string) $object['Key'], 'version' => null, 'size' => (int) ($object['Size'] ?? 0)];
                }
                $token = !empty($page['IsTruncated']) ? ($page['NextContinuationToken'] ?? null) : null;
            } while ($token !== null);

            return;
        }

        $keyMarker = null;
        $versionMarker = null;
        do {
            $page = $client->listObjectVersions(array_filter([
                'Bucket'          => $bucket,
                'Prefix'          => $prefix,
                'KeyMarker'       => $keyMarker,
                'VersionIdMarker' => $versionMarker,
            ]));
            foreach (['Versions', 'DeleteMarkers'] as $group) {
                foreach ($page[$group] ?? [] as $object) {
                    yield [
                        'key'     => (string) $object['Key'],
                        'version' => isset($object['VersionId']) ? (string) $object['VersionId'] : null,
                        'size'    => (int) ($object['Size'] ?? 0),
                    ];
                }
            }
            $truncated     = !empty($page['IsTruncated']);
            $keyMarker     = $truncated ? ($page['NextKeyMarker'] ?? null) : null;
            $versionMarker = $truncated ? ($page['NextVersionIdMarker'] ?? null) : null;
        } while ($keyMarker !== null);
    }

    private function deleteBatch(S3Client $client, string $bucket, array $objects): int
    {
        $result = $client->deleteObjects([
            'Bucket' => $bucket,
            'Delete' => ['Objects' => $objects, 'Quiet' => true],
        ]);

        $errors = $result['Errors'] ?? [];
        if (!empty($errors)) {
            $first = $errors[0];
            throw new \RuntimeException(sprintf(
                'حذف %d آبجکت ناموفق بود (نمونه: %s — %s)',
                count($errors),
                $first['Key'] ?? '?',
                $first['Code'] ?? ($first['Message'] ?? 'error'),
            ));
        }

        return count($objects);
    }
}
