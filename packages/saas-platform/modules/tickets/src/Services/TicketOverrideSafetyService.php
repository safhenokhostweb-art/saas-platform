<?php

namespace SaaSPlatform\Modules\Tickets\Services;

/**
 * PHASE 7.1 — TASK 5: Ticket Override Safety Service
 *
 * Ensures customer-published overrides survive module updates.
 * Follows Forms module pattern.
 */
class TicketOverrideSafetyService
{
    public function snapshotPublishedFiles(): array
    {
        $snapshot = [];
        $paths    = [
            config_path('tickets.php'),
            resource_path('views/vendor/tickets'),
        ];

        foreach ($paths as $path) {
            if (is_file($path)) {
                $snapshot[$path] = hash_file('sha256', $path);
            } elseif (is_dir($path)) {
                $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
                foreach ($iter as $file) {
                    if ($file->isFile()) {
                        $snapshot[$file->getPathname()] = hash_file('sha256', $file->getPathname());
                    }
                }
            }
        }

        return $snapshot;
    }

    public function detectDrift(array $snapshot): array
    {
        $drifted = [];
        foreach ($snapshot as $path => $hash) {
            if (!file_exists($path)) {
                $drifted[] = $path . ' (deleted)';
            } elseif (hash_file('sha256', $path) !== $hash) {
                $drifted[] = $path . ' (modified unexpectedly)';
            }
        }
        return $drifted;
    }

    public function hasPublishedViews(): bool
    {
        return is_dir(resource_path('views/vendor/tickets'));
    }

    public function hasPublishedConfig(): bool
    {
        return file_exists(config_path('tickets.php'));
    }
}
