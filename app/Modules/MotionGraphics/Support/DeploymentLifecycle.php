<?php

namespace App\Modules\MotionGraphics\Support;

use App\Modules\MotionGraphics\DTO\DeploymentManifest;

/**
 * The deployment state lifecycle (task scope item 6):
 *
 * ```text
 * Prepared -> Validated -> Approved -> Ready -> (future) Deployed
 * ```
 *
 * `Deployed` is reserved — no code path in this phase ever transitions
 * to it; `canTransition()` still models it so a future phase's
 * activation step has a defined, reviewable transition to implement
 * rather than inventing one.
 */
final class DeploymentLifecycle
{
    private const TRANSITIONS = [
        DeploymentManifest::STATUS_PREPARED => [DeploymentManifest::STATUS_VALIDATED],
        DeploymentManifest::STATUS_VALIDATED => [DeploymentManifest::STATUS_APPROVED],
        DeploymentManifest::STATUS_APPROVED => [DeploymentManifest::STATUS_READY],
        DeploymentManifest::STATUS_READY => [DeploymentManifest::STATUS_DEPLOYED],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
