<?php

namespace App\Services\CustomerInstallation\State;

final class StateNormalizer
{
    private const LEGACY_TO_CANONICAL = [
        'pending' => StateResolver::PENDING,
        'running' => StateResolver::RUNNING,
        'complete' => StateResolver::COMPLETE,
        'failed' => StateResolver::FAILED,
        'rolling_back' => StateResolver::ROLLING_BACK,
        'rolled_back' => StateResolver::ROLLED_BACK,
        'rollback_failed' => StateResolver::ROLLBACK_FAILED,
        'deleted' => StateResolver::DELETED,
        'installation_ready' => StateResolver::INSTALLATION_READY,
        'installation_blocked' => StateResolver::INSTALLATION_BLOCKED,
        'verify_success' => StateResolver::VERIFY_SUCCESS,
        'verify_failed' => StateResolver::VERIFY_FAILED,
        'customer_ready' => StateResolver::CUSTOMER_READY,
    ];

    private const CANONICAL_TO_LEGACY_STORAGE = [
        StateResolver::PENDING => 'pending',
        StateResolver::RUNNING => 'running',
        StateResolver::COMPLETE => 'complete',
        StateResolver::FAILED => 'failed',
        StateResolver::ROLLING_BACK => 'rolling_back',
        StateResolver::ROLLED_BACK => 'rolled_back',
        StateResolver::ROLLBACK_FAILED => 'rollback_failed',
        StateResolver::DELETED => 'deleted',
    ];

    public function normalize(string $state): string
    {
        $normalized = strtolower(trim($state));

        return self::LEGACY_TO_CANONICAL[$normalized] ?? strtoupper($normalized);
    }

    public function legacyStorageValue(string $canonicalState): string
    {
        return self::CANONICAL_TO_LEGACY_STORAGE[$canonicalState] ?? $canonicalState;
    }
}
