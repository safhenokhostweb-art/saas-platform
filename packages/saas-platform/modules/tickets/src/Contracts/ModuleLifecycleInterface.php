<?php

namespace SaaSPlatform\Modules\Tickets\Contracts;

/**
 * Canonical module lifecycle contract for the Tickets module.
 *
 * Mirrors the Forms module interface (PHASE 6.95 reference implementation).
 * All methods are called by ModuleUpdateOrchestrator during the install,
 * activate, update, and rollback lifecycle stages.
 */
interface ModuleLifecycleInterface
{
    public function install(): bool;

    public function uninstall(): bool;

    public function activate(int $siteId): bool;

    public function deactivate(int $siteId): bool;

    public function update(string $fromVersion, string $toVersion): bool;

    public function rollback(string $currentVersion, string $targetVersion): bool;

    public function getVersion(): string;

    public function getSlug(): string;
}
