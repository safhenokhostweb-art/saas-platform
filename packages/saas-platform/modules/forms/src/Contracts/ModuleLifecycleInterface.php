<?php

namespace SaaSPlatform\Modules\Forms\Contracts;

/**
 * Canonical module lifecycle contract.
 *
 * Every module that wants production-grade lifecycle management
 * must implement this interface. The methods are called by the
 * ModuleLifecycleService during install, uninstall, activate,
 * deactivate, update, and rollback operations.
 *
 * This is the OFFICIAL REFERENCE implementation for all future modules.
 */
interface ModuleLifecycleInterface
{
    /**
     * Fired when the module is first installed.
     * Should publish config, views, migrations, and run any setup.
     */
    public function install(): bool;

    /**
     * Fired when the module is fully uninstalled.
     * Should clean up all module data, config, and published assets.
     */
    public function uninstall(): bool;

    /**
     * Fired when the module is activated for a specific tenant/site.
     */
    public function activate(int $siteId): bool;

    /**
     * Fired when the module is deactivated for a specific tenant/site.
     */
    public function deactivate(int $siteId): bool;

    /**
     * Fired when the module version is updated.
     * Receives the previous version for migration logic.
     */
    public function update(string $fromVersion, string $toVersion): bool;

    /**
     * Fired when a module update is rolled back.
     * Receives the version being rolled back to.
     */
    public function rollback(string $currentVersion, string $targetVersion): bool;

    /**
     * Returns the current installed version of the module.
     */
    public function getVersion(): string;

    /**
     * Returns the module slug identifier.
     */
    public function getSlug(): string;
}
