<?php

namespace App\Services\CustomerDeletion\Guards;

/**
 * ADR-090 — a customer database / database user may be dropped only when its
 * name is a plain identifier and is not a MySQL system schema/account or any
 * database/user the platform itself is configured with.
 */
final class DatabaseNameGuard
{
    private const SYSTEM_DATABASES = ['mysql', 'sys', 'information_schema', 'performance_schema'];

    private const SYSTEM_USERS = ['root', 'mysql', 'mysql.sys', 'mysql.session', 'mysql.infoschema', 'debian-sys-maint'];

    public static function assertDatabase(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $name)) {
            throw new PurgeGuardViolation("نام پایگاه داده معتبر نیست: «{$name}»");
        }

        $protected = array_merge(self::SYSTEM_DATABASES, self::configured('database'));
        if (in_array(strtolower($name), $protected, true)) {
            throw new PurgeGuardViolation("پایگاه داده «{$name}» محافظت‌شده است و حذف نمی‌شود.");
        }

        return $name;
    }

    public static function assertUser(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]{1,32}$/', $name)) {
            throw new PurgeGuardViolation("نام کاربر پایگاه داده معتبر نیست: «{$name}»");
        }

        $protected = array_merge(self::SYSTEM_USERS, self::configured('username'));
        if (in_array(strtolower($name), $protected, true)) {
            throw new PurgeGuardViolation("کاربر پایگاه داده «{$name}» محافظت‌شده است و حذف نمی‌شود.");
        }

        return $name;
    }

    /** Every database / username in config('database.connections'), lowercased. */
    private static function configured(string $key): array
    {
        $values = [];
        foreach ((array) config('database.connections', []) as $connection) {
            $value = is_array($connection) ? ($connection[$key] ?? null) : null;
            if (is_string($value) && $value !== '') {
                $values[] = strtolower($value);
            }
        }

        return array_values(array_unique($values));
    }
}
