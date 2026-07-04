<?php

namespace App\Services\CustomerInstallation\State;

use App\Models\CustomerInstallation;

final class InstallationStateBridge
{
    private readonly StateResolver $resolver;

    public function __construct(
        ?StateResolver $resolver = null,
    ) {
        $this->resolver = $resolver ?? new StateResolver();
    }

    public function read(CustomerInstallation $installation): string
    {
        $overlayState = $this->resolveOverlay($installation);
        if ($overlayState !== null) {
            return $overlayState;
        }

        return $this->resolveDatabaseState($installation);
    }

    public function transition(CustomerInstallation $installation, string $nextState): string
    {
        $overlayState = $this->resolveOverlay($installation);
        if ($overlayState !== null) {
            return $this->resolver->transition($overlayState, $nextState);
        }

        // DB-only records retain legacy transition behavior while both states
        // still pass through overlay normalization and known-state validation.
        $this->resolveDatabaseState($installation);

        return $this->resolver->resolveOrInvalid($nextState);
    }

    public function storageValue(string $state): string
    {
        return $this->resolver->storageValue($state);
    }

    private function resolveOverlay(CustomerInstallation $installation): ?string
    {
        $overlayState = data_get($installation->metadata, 'installation_state');

        if (!is_string($overlayState) || trim($overlayState) === '') {
            return null;
        }

        return $this->resolver->resolveOrInvalid($overlayState);
    }

    private function resolveDatabaseState(CustomerInstallation $installation): string
    {
        $databaseState = $installation->exists
            ? $installation->newQuery()->whereKey($installation->getKey())->value('status')
            : $installation->getAttribute('status');

        return $this->resolver->resolveOrFailed($databaseState);
    }
}
