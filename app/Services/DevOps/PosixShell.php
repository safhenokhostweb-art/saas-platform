<?php

namespace App\Services\DevOps;

/**
 * Commands built here always run on a remote POSIX shell, so arguments are
 * single-quoted the POSIX way regardless of the panel's own OS. PHP's
 * escapeshellarg() switches to Windows double-quote rules on a Windows host,
 * where `$` and backticks would still expand on the remote side.
 */
final class PosixShell
{
    public static function quote(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }
}
