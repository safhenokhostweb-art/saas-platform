<?php

namespace SaaSPlatform\CoreUtils\Contracts\Services;

/**
 * Phase 17-F — Part 6: contract only. No implementation exists yet, and
 * nothing calls this interface anywhere in this phase — per the explicit
 * scope, real uninstall execution is a later phase's work. This defines
 * the shape a future implementation must satisfy, mirroring
 * module.json's `uninstall` manifest field:
 *
 * ```json
 * "uninstall": {
 *     "type": "soft",
 *     "cleanup": ["tables", "files", "permissions"]
 * }
 * ```
 *
 * TYPE_SOFT — disable the module's entitlement, keep all customer data
 * (tables, files) in place. Reversible: re-installing restores access to
 * the same data.
 *
 * TYPE_HARD — remove the module's data per the declared `cleanup` targets.
 * Not reversible: re-installing starts from a clean state.
 */
interface ModuleUninstallStrategyInterface
{
    public const TYPE_SOFT = 'soft';
    public const TYPE_HARD = 'hard';

    public const CLEANUP_TABLES = 'tables';
    public const CLEANUP_FILES = 'files';
    public const CLEANUP_PERMISSIONS = 'permissions';

    /**
     * @param  string  $type  ModuleUninstallStrategyInterface::TYPE_SOFT|TYPE_HARD
     * @param  string[]  $cleanup  subset of CLEANUP_* constants, only meaningful when $type === TYPE_HARD
     */
    public function uninstall(string $moduleSlug, int $siteId, string $type, array $cleanup): void;
}
