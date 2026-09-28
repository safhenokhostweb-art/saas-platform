<?php

namespace SaaSPlatform\CoreUtils\SEO;

enum MetaContext: string
{
    case PLATFORM_ADMIN = 'platform_admin';
    case PLATFORM_PUBLIC = 'platform_public';
    case TENANT_ADMIN = 'tenant_admin';
    case TENANT_PUBLIC = 'tenant_public';
    case CANVAS_PUBLIC = 'canvas_public';
    case PREVIEW_EDITOR = 'preview_editor';
    case AUTH_INSTALLED = 'auth_installed';

    public function isPlatform(): bool
    {
        return match ($this) {
            self::PLATFORM_ADMIN, self::PLATFORM_PUBLIC => true,
            default => false,
        };
    }

    public function isTenant(): bool
    {
        return match ($this) {
            self::TENANT_ADMIN, self::TENANT_PUBLIC, self::CANVAS_PUBLIC => true,
            default => false,
        };
    }

    public function defaultRobots(): string
    {
        return match ($this) {
            self::PLATFORM_PUBLIC, self::TENANT_PUBLIC, self::CANVAS_PUBLIC => 'index, follow',
            default => 'noindex, nofollow',
        };
    }
}
