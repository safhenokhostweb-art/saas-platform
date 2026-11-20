<?php

namespace App\Services\CustomerDeletion\Guards;

/**
 * Thrown when a destructive purge target (remote path, object-storage prefix,
 * database name, certificate name) fails a safety guard (ADR-090). Callers
 * turn it into a "manual action required" outcome or a failed stage — a
 * guard violation never falls through to the destructive command.
 */
class PurgeGuardViolation extends \RuntimeException
{
}
