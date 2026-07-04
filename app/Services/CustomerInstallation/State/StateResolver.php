<?php

namespace App\Services\CustomerInstallation\State;

final class StateResolver
{
    public const PENDING = 'PENDING';
    public const RUNNING = 'RUNNING';
    public const COMPLETE = 'COMPLETE';
    public const FAILED = 'FAILED';
    public const ROLLING_BACK = 'ROLLING_BACK';
    public const ROLLED_BACK = 'ROLLED_BACK';
    public const ROLLBACK_FAILED = 'ROLLBACK_FAILED';
    public const DELETED = 'DELETED';
    public const INSTALLATION_READY = 'INSTALLATION_READY';
    public const INSTALLATION_BLOCKED = 'INSTALLATION_BLOCKED';
    public const VERIFY_SUCCESS = 'VERIFY_SUCCESS';
    public const VERIFY_FAILED = 'VERIFY_FAILED';
    public const CUSTOMER_READY = 'CUSTOMER_READY';
    public const INVALID_STATE = 'INVALID_STATE';

    public const STATES = [
        self::PENDING,
        self::RUNNING,
        self::COMPLETE,
        self::FAILED,
        self::ROLLING_BACK,
        self::ROLLED_BACK,
        self::ROLLBACK_FAILED,
        self::DELETED,
        self::INSTALLATION_READY,
        self::INSTALLATION_BLOCKED,
        self::VERIFY_SUCCESS,
        self::VERIFY_FAILED,
        self::CUSTOMER_READY,
        self::INVALID_STATE,
    ];

    private StateNormalizer $normalizer;
    private StateValidator $validator;

    public function __construct(
        ?StateNormalizer $normalizer = null,
        ?StateValidator $validator = null,
    ) {
        $this->normalizer = $normalizer ?? new StateNormalizer();
        $this->validator = $validator ?? new StateValidator();
    }

    public function resolve(string $state): string
    {
        $canonical = $this->normalizer->normalize($state);
        $this->validator->assertKnown($canonical);

        return $canonical;
    }

    public function resolveOrInvalid(string $state): string
    {
        try {
            return $this->resolve($state);
        } catch (\InvalidArgumentException) {
            return self::INVALID_STATE;
        }
    }

    public function resolveOrFailed(mixed $state): string
    {
        if (!is_string($state) || trim($state) === '') {
            return self::FAILED;
        }

        $resolved = $this->resolveOrInvalid($state);

        return $resolved === self::INVALID_STATE ? self::FAILED : $resolved;
    }

    public function storageValue(string $state): string
    {
        return $this->normalizer->legacyStorageValue($this->resolve($state));
    }

    public function transition(string $currentState, string $nextState): string
    {
        $current = $this->resolveOrInvalid($currentState);
        $next = $this->resolveOrInvalid($nextState);

        if ($current === self::INVALID_STATE || $next === self::INVALID_STATE) {
            return self::INVALID_STATE;
        }

        $this->validator->assertTransition($current, $next);

        return $next;
    }
}
