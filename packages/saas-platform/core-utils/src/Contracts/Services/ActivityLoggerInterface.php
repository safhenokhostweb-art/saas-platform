<?php

namespace SaaSPlatform\CoreUtils\Contracts\Services;

/**
 * Contract for activity logging.
 * Packages inject this to log domain events without importing App\Services\ActivityLogger.
 * The host app provides the concrete implementation.
 */
interface ActivityLoggerInterface
{
    /**
     * @param string       $action      Event key (e.g. 'form.created')
     * @param string|null  $description Human-readable description
     * @param object|null  $subject     Related model (passed as generic object to avoid App\ type-hint)
     * @param array        $properties  Additional context data
     */
    public function log(
        string $action,
        ?string $description = null,
        ?object $subject = null,
        array $properties = []
    ): void;
}
