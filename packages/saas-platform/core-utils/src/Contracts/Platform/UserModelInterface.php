<?php

namespace SaaSPlatform\CoreUtils\Contracts\Platform;

/**
 * PHASE 8.0 — Minimal interface for the platform User model.
 *
 * Packages MUST import this interface instead of App\Models\User directly.
 */
interface UserModelInterface
{
    public function getId(): int;
    public function getKey(): mixed;
    public function getName(): ?string;
    public function getEmail(): ?string;
    public function isAdmin(): bool;
}
