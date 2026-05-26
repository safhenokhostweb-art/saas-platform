<?php

namespace App\Adapters;

use SaaSPlatform\CoreUtils\Contracts\Platform\UserModelInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\AuthUserResolverInterface;

class AuthUserResolverAdapter implements AuthUserResolverInterface
{
    public function id(): ?int
    {
        return auth()->id();
    }

    public function check(): bool
    {
        return auth()->check();
    }

    public function user(): ?UserModelInterface
    {
        $user = auth()->user();
        return $user instanceof UserModelInterface ? $user : null;
    }

    public function isAdmin(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        return $user instanceof UserModelInterface
            ? $user->isAdmin()
            : (bool) ($user->is_admin ?? false);
    }
}
