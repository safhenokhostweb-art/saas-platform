<?php

namespace App\Services\CustomerDeletion\Guards;

/**
 * ADR-090 — object-storage prefixes a site purge may delete.
 *
 * Prefixes are never accepted from input or from free-form patterns: they
 * are the exact strings built here from a positive integer site id, and a
 * caller's prefix must be identical to one of them. Every prefix ends with a
 * delimiter (`/`, or `_` for the flat archive names) so `sites/1/` can never
 * match `sites/10/…`, and every listed key is re-checked against its prefix
 * before it is deleted.
 */
final class ObjectPrefixGuard
{
    /** Private backup bucket (safhenokhost-backups). */
    public static function backupPrefixes(int $siteId): array
    {
        $id = self::id($siteId);

        return [
            "tenants/site-{$id}/",                 // db:backup-sites (pull model)
            "customers/site-{$id}/",               // install baselines / final delete backups
            "customer-sites/tenant-{$id}/",        // legacy tenant backups
            "saas-platform/archives/site_{$id}_",  // SiteArchiveService archive zips
        ];
    }

    /** Tenant media bucket (safhenokhost-media): sites/{id}/media and sites/{id}/private. */
    public static function mediaPrefixes(int $siteId): array
    {
        return ['sites/' . self::id($siteId) . '/'];
    }

    public static function assertBackupPrefix(string $prefix, int $siteId): string
    {
        return self::assertOneOf($prefix, self::backupPrefixes($siteId));
    }

    public static function assertMediaPrefix(string $prefix, int $siteId): string
    {
        return self::assertOneOf($prefix, self::mediaPrefixes($siteId));
    }

    public static function assertKeyWithin(string $key, string $prefix): void
    {
        if ($prefix === '' || !str_starts_with($key, $prefix) || str_contains($key, '..')) {
            throw new PurgeGuardViolation("کلید «{$key}» خارج از پیشوند «{$prefix}» است.");
        }
    }

    private static function assertOneOf(string $prefix, array $allowed): string
    {
        if (!in_array($prefix, $allowed, true)) {
            throw new PurgeGuardViolation("پیشوند «{$prefix}» در فهرست مجاز این سایت نیست.");
        }

        return $prefix;
    }

    private static function id(int $siteId): int
    {
        if ($siteId <= 0) {
            throw new PurgeGuardViolation('شناسهٔ سایت باید عدد مثبت باشد.');
        }

        return $siteId;
    }
}
