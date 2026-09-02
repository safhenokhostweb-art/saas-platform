<?php

namespace SaaSPlatform\ThemeRuntime;

use RuntimeException;

/**
 * Phase 3E-B (ADR-030) — thrown by BlockRegistry::registerHostType() when the
 * requested type identifier is already claimed, either by a canonical
 * definition (never overridable by any host/extension registration) or by a
 * prior host registration with a genuinely different definition. Fail
 * closed: registration never mutates registry state when this is thrown, and
 * no silent first-wins/last-wins fallback is ever applied.
 */
final class BlockTypeAlreadyRegisteredException extends RuntimeException
{
    public static function canonicalCollision(string $type): self
    {
        return new self(
            "Cannot register host type \"{$type}\" — it is already a canonical block type. "
            . 'Canonical type identifiers are never overridable by a host or extension registration.'
        );
    }

    public static function hostCollision(string $type): self
    {
        return new self(
            "Cannot register host type \"{$type}\" — it is already registered with a different definition. "
            . 'Re-registering a host type with a different definition is rejected, not silently replaced.'
        );
    }
}
