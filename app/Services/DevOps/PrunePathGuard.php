<?php

namespace App\Services\DevOps;

use App\Services\DevOps\Exceptions\UnsafePrunePathException;

/**
 * Pure path validation for the storage pruner. Nothing here touches a
 * filesystem: remote paths are checked as strings, before and after the
 * remote host reports them, so a hostile or broken listing can never widen
 * what gets deleted.
 *
 * Rules:
 *  - a web root is an absolute, normalized POSIX path, at least two levels
 *    deep, and never a system directory;
 *  - a candidate must sit strictly inside one of the pruner's fixed target
 *    directories and outside every protected (media / public) directory;
 *  - no control characters, no "." / ".." segments, no "//", no .gitignore.
 */
class PrunePathGuard
{
    /** Never acceptable as a web root, even if a server record says so. */
    private const FORBIDDEN_ROOTS = [
        '/', '/bin', '/boot', '/dev', '/etc', '/home', '/lib', '/lib64', '/media',
        '/mnt', '/opt', '/proc', '/root', '/run', '/sbin', '/srv', '/sys', '/tmp',
        '/usr', '/var', '/var/www', '/var/lib', '/var/log', '/var/tmp',
    ];

    /**
     * Relative to the web root. Permanent customer content lives here; the
     * pruner's fixed targets never overlap them, and this list makes sure a
     * future target edit cannot either.
     */
    public const PROTECTED_RELATIVE = [
        'public',
        'storage/app/public',
        'storage/app/uploads',
        'storage/app/media',
        'storage/app/private',
        'storage/app/theme-assets',
    ];

    public function assertSafeWebRoot(string $root): string
    {
        $root = rtrim($root, '/');

        if (!$this->isNormalizedAbsolute($root)) {
            throw new UnsafePrunePathException("Web root is not a normalized absolute path: {$this->printable($root)}");
        }

        if (in_array($root, self::FORBIDDEN_ROOTS, true)) {
            throw new UnsafePrunePathException("Web root points at a system directory: {$root}");
        }

        if (count(explode('/', ltrim($root, '/'))) < 2) {
            throw new UnsafePrunePathException("Web root is too shallow to be a site directory: {$root}");
        }

        return $root;
    }

    /**
     * Resolve one of the pruner's fixed relative targets under a validated
     * root. The relative path must itself be clean and must stay in storage/.
     */
    public function targetDirectory(string $root, string $relative): string
    {
        $relative = trim($relative, '/');

        if ($relative === '' || !$this->isNormalizedAbsolute('/' . $relative)) {
            throw new UnsafePrunePathException("Invalid prune target: {$this->printable($relative)}");
        }

        if (!str_starts_with($relative . '/', 'storage/')) {
            throw new UnsafePrunePathException("Prune targets must live under storage/: {$relative}");
        }

        foreach (self::PROTECTED_RELATIVE as $protected) {
            if ($this->isSameOrInside($relative, $protected) || $this->isSameOrInside($protected, $relative)) {
                throw new UnsafePrunePathException("Prune target overlaps protected directory {$protected}: {$relative}");
            }
        }

        return $root . '/' . $relative;
    }

    /**
     * Whether a path reported by the remote host may be deleted.
     *
     * @return string|null null when safe, otherwise the rejection reason
     */
    public function rejectionReason(string $path, string $targetDir, string $root): ?string
    {
        if (!$this->isNormalizedAbsolute($path)) {
            return 'not_normalized';
        }

        if (!str_starts_with($path, $targetDir . '/')) {
            return 'outside_target';
        }

        foreach (self::PROTECTED_RELATIVE as $protected) {
            if ($this->isSameOrInside($path, $root . '/' . $protected)) {
                return 'protected';
            }
        }

        if (basename($path) === '.gitignore') {
            return 'gitignore';
        }

        return null;
    }

    public function isSafeCandidate(string $path, string $targetDir, string $root): bool
    {
        return $this->rejectionReason($path, $targetDir, $root) === null;
    }

    private function isNormalizedAbsolute(string $path): bool
    {
        if ($path === '' || $path[0] !== '/') {
            return false;
        }

        // Control characters (incl. NUL, tab, newline) would break shell
        // quoting assumptions and the NUL-separated listing format.
        if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return false;
        }

        if ($path !== '/' && (str_contains($path, '//') || str_ends_with($path, '/'))) {
            return false;
        }

        foreach (explode('/', substr($path, 1)) as $segment) {
            if ($segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private function isSameOrInside(string $path, string $parent): bool
    {
        return $path === $parent || str_starts_with($path, $parent . '/');
    }

    private function printable(string $value): string
    {
        return addcslashes($value, "\0..\37\177");
    }
}
