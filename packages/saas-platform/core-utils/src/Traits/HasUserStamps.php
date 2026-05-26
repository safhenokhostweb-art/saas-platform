<?php

namespace SaaSPlatform\CoreUtils\Traits;

use SaaSPlatform\CoreUtils\Contracts\Services\AuthUserResolverInterface;

trait HasUserStamps
{
    protected static function bootHasUserStamps(): void
    {
        static::creating(function ($model) {
            $auth = static::resolveAuthUserStamps();
            if ($auth->check()) {
                $model->created_by = $auth->id();
            }
        });

        static::updating(function ($model) {
            $auth = static::resolveAuthUserStamps();
            if ($auth->check()) {
                $model->updated_by = $auth->id();
            }
        });
    }

    private static function resolveAuthUserStamps(): AuthUserResolverInterface
    {
        if (app()->bound(AuthUserResolverInterface::class)) {
            return app(AuthUserResolverInterface::class);
        }

        // Inline fallback when the contract is not bound (package used standalone)
        return new class implements AuthUserResolverInterface {
            public function check(): bool { return auth()->check(); }
            public function id(): ?int { return auth()->id(); }
            public function user(): ?\SaaSPlatform\CoreUtils\Contracts\Platform\UserModelInterface { return null; }
            public function isAdmin(): bool { return (bool) (auth()->user()?->is_admin ?? false); }
        };
    }
}
