<?php

namespace App\Services\CustomerDeletion\Guards;

/**
 * ADR-090 — the only gate in front of `rm -rf` on a customer server.
 *
 * A path is deletable only when it is the exact, per-installation directory
 * the platform itself created:
 *
 *  - VPS family: `/var/www/{slug}` where {slug} is the installation's own
 *    `cust_{segment}_{installationId}` (DeleteContext::resolveSlug()), so a
 *    server `path` override like `/var/www` or `/var/www/html` can never be
 *    reached, and neither can another installation's directory.
 *  - Shared hosting (cPanel/DirectAdmin): a directory strictly inside
 *    `/home/{username}/` that is not the account root, `public_html` or any
 *    other account-level directory. Whether it is unique to this
 *    installation is checked by the plan builder, not here.
 *
 * Everything is checked on the string itself (absolute, normalised, no `..`,
 * no shell metacharacters); the remote `readlink -f` comparison done by the
 * stage additionally rules out a symlink pointing elsewhere.
 */
final class RemotePathGuard
{
    public const VPS_BASE = '/var/www';

    /** Never deletable, whatever the caller believes. */
    private const FORBIDDEN = [
        '/', '/bin', '/boot', '/dev', '/etc', '/home', '/lib', '/lib64', '/opt', '/proc',
        '/root', '/run', '/sbin', '/srv', '/sys', '/tmp', '/usr', '/var', '/var/backups',
        '/var/lib', '/var/log', '/var/www', '/var/www/html',
    ];

    /** Account-level directories under /home/{user} that are never a site root. */
    private const SHARED_HOSTING_RESERVED = [
        'public_html', 'private_html', 'public_ftp', 'www', 'domains', 'mail', 'etc',
        'ssl', 'logs', 'tmp', 'backups', 'cgi-bin',
    ];

    public const SLUG_PATTERN = '/^cust_[a-z0-9_]{1,20}_[1-9][0-9]*$/';

    /** Shape checks shared by every rule; returns the path without a trailing slash. */
    public static function assertSafeShape(string $path): string
    {
        if ($path === '' || $path[0] !== '/') {
            throw new PurgeGuardViolation('مسیر باید مطلق باشد: «' . $path . '»');
        }

        $normalized = rtrim($path, '/');

        if ($normalized === '' || strlen($normalized) > 255) {
            throw new PurgeGuardViolation('مسیر خالی یا بیش از حد طولانی است.');
        }

        // Only plain path characters: no spaces, globs, quotes, $, ;, |, newlines.
        if (!preg_match('#^/[A-Za-z0-9._/-]+$#', $normalized)) {
            throw new PurgeGuardViolation('مسیر شامل کاراکتر غیرمجاز است: «' . $normalized . '»');
        }

        foreach (explode('/', substr($normalized, 1)) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new PurgeGuardViolation('مسیر نرمال نیست (//، . یا ..): «' . $normalized . '»');
            }
        }

        if (in_array($normalized, self::FORBIDDEN, true)) {
            throw new PurgeGuardViolation('حذف این مسیر سیستمی هرگز مجاز نیست: «' . $normalized . '»');
        }

        if (substr_count($normalized, '/') < 2) {
            throw new PurgeGuardViolation('مسیر سطح بالا قابل حذف نیست: «' . $normalized . '»');
        }

        return $normalized;
    }

    public static function assertVpsSiteRoot(string $path, string $expectedSlug): string
    {
        $normalized = self::assertSafeShape($path);

        if (!preg_match(self::SLUG_PATTERN, $expectedSlug)) {
            throw new PurgeGuardViolation('شناسهٔ نصب (slug) معتبر نیست: «' . $expectedSlug . '»');
        }

        if ($normalized !== self::VPS_BASE . '/' . $expectedSlug) {
            throw new PurgeGuardViolation(
                'مسیر «' . $normalized . '» پوشهٔ اختصاصی این نصب (' . self::VPS_BASE . '/' . $expectedSlug . ') نیست.'
            );
        }

        return $normalized;
    }

    public static function assertSharedHostingSiteRoot(string $path, string $username): string
    {
        $normalized = self::assertSafeShape($path);

        if (!preg_match('/^[a-z][a-z0-9_-]{0,31}$/i', $username)) {
            throw new PurgeGuardViolation('نام کاربری هاست معتبر نیست.');
        }

        $home = '/home/' . $username;
        if (!str_starts_with($normalized, $home . '/')) {
            throw new PurgeGuardViolation('مسیر «' . $normalized . '» داخل حساب ' . $home . ' نیست.');
        }

        $relative = substr($normalized, strlen($home) + 1);
        $first = explode('/', $relative)[0];

        if ($relative === '' || str_starts_with($first, '.') || in_array(strtolower($relative), self::SHARED_HOSTING_RESERVED, true)) {
            throw new PurgeGuardViolation('«' . $normalized . '» پوشهٔ سطح حساب هاست است، نه پوشهٔ اختصاصی سایت.');
        }

        return $normalized;
    }

    /** nginx vhost file names are the installation slug and nothing else. */
    public static function assertVhostPath(string $path, string $expectedSlug, string $directory): string
    {
        if (!preg_match(self::SLUG_PATTERN, $expectedSlug) || $path !== $directory . '/' . $expectedSlug) {
            throw new PurgeGuardViolation('مسیر پیکربندی Nginx با شناسهٔ این نصب مطابقت ندارد: «' . $path . '»');
        }

        return $path;
    }
}
