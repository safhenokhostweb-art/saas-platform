<?php

namespace SaaSPlatform\CoreUtils\Services\Deployment;

/**
 * Canonical deployment lifecycle state machine.
 *
 * Valid states and allowed transitions:
 *
 *   pending → validating → maintenance → migrations → assets
 *           → cache-rebuild → queue-restart → health-check → finalized
 *
 * Failure path (from any state):
 *   * → rollback → recovery → unlock → failed
 */
class DeploymentStateMachine
{
    public const STATE_PENDING        = 'pending';
    public const STATE_VALIDATING     = 'validating';
    public const STATE_MAINTENANCE    = 'maintenance';
    public const STATE_MIGRATIONS     = 'migrations';
    public const STATE_ASSETS         = 'assets';
    public const STATE_CACHE_REBUILD  = 'cache-rebuild';
    public const STATE_QUEUE_RESTART  = 'queue-restart';
    public const STATE_HEALTH_CHECK   = 'health-check';
    public const STATE_FINALIZED      = 'finalized';
    public const STATE_ROLLBACK       = 'rollback';
    public const STATE_RECOVERY       = 'recovery';
    public const STATE_UNLOCK         = 'unlock';
    public const STATE_FAILED         = 'failed';

    private const TRANSITIONS = [
        self::STATE_PENDING       => [self::STATE_VALIDATING, self::STATE_FAILED],
        self::STATE_VALIDATING    => [self::STATE_MAINTENANCE, self::STATE_FAILED, self::STATE_ROLLBACK],
        self::STATE_MAINTENANCE   => [self::STATE_MIGRATIONS, self::STATE_ROLLBACK, self::STATE_FAILED],
        self::STATE_MIGRATIONS    => [self::STATE_ASSETS, self::STATE_ROLLBACK, self::STATE_FAILED],
        self::STATE_ASSETS        => [self::STATE_CACHE_REBUILD, self::STATE_ROLLBACK, self::STATE_FAILED],
        self::STATE_CACHE_REBUILD => [self::STATE_QUEUE_RESTART, self::STATE_ROLLBACK, self::STATE_FAILED],
        self::STATE_QUEUE_RESTART => [self::STATE_HEALTH_CHECK, self::STATE_ROLLBACK, self::STATE_FAILED],
        self::STATE_HEALTH_CHECK  => [self::STATE_FINALIZED, self::STATE_ROLLBACK, self::STATE_FAILED],
        self::STATE_FINALIZED     => [],
        self::STATE_ROLLBACK      => [self::STATE_RECOVERY, self::STATE_FAILED],
        self::STATE_RECOVERY      => [self::STATE_UNLOCK, self::STATE_FAILED],
        self::STATE_UNLOCK        => [self::STATE_FAILED],
        self::STATE_FAILED        => [],
    ];

    private string $current;

    public function __construct(string $initial = self::STATE_PENDING)
    {
        $this->current = $initial;
    }

    public function current(): string
    {
        return $this->current;
    }

    public function canTransitionTo(string $state): bool
    {
        return in_array($state, self::TRANSITIONS[$this->current] ?? [], true);
    }

    public function transition(string $state): void
    {
        if (!$this->canTransitionTo($state)) {
            throw new \LogicException(
                "Invalid transition from '{$this->current}' to '{$state}'."
            );
        }
        $this->current = $state;
    }

    public function isTerminal(): bool
    {
        return in_array($this->current, [self::STATE_FINALIZED, self::STATE_FAILED], true);
    }

    public function isFailure(): bool
    {
        return in_array($this->current, [self::STATE_ROLLBACK, self::STATE_RECOVERY, self::STATE_UNLOCK, self::STATE_FAILED], true);
    }

    public function orderedPhases(): array
    {
        return [
            self::STATE_PENDING,
            self::STATE_VALIDATING,
            self::STATE_MAINTENANCE,
            self::STATE_MIGRATIONS,
            self::STATE_ASSETS,
            self::STATE_CACHE_REBUILD,
            self::STATE_QUEUE_RESTART,
            self::STATE_HEALTH_CHECK,
            self::STATE_FINALIZED,
        ];
    }
}
