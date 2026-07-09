<?php

namespace App\Services\ModuleEcosystem\Dto;

/**
 * Output shape for VisibilityResolverInterface::resolve().
 *
 * The single computed display state a customer runtime should show for
 * a module, plus optional metadata (e.g. update_type, deny_reason) for
 * callers that need more than the state alone.
 */
readonly class VisibilityState
{
    public const STATE_ACTIVE    = 'active';
    public const STATE_LOCKED    = 'locked';
    public const STATE_AVAILABLE = 'available';
    public const STATE_HIDDEN    = 'hidden';

    public function __construct(
        public string $state,
        public array  $metadata = [],
    ) {}
}
