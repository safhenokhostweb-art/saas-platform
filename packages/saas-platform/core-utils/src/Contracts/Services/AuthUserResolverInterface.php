<?php

namespace SaaSPlatform\CoreUtils\Contracts\Services;

use SaaSPlatform\CoreUtils\Contracts\Platform\UserModelInterface;

/**
 * Contract for resolving the currently authenticated user.
 * Packages inject this instead of calling auth() directly, which
 * prevents assumptions about the host app's User model structure.
 */
interface AuthUserResolverInterface
{
    public function id(): ?int;

    public function check(): bool;

    public function user(): ?UserModelInterface;

    public function isAdmin(): bool;
}
