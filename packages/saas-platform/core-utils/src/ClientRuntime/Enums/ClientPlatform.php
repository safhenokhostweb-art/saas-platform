<?php

namespace SaaSPlatform\CoreUtils\ClientRuntime\Enums;

enum ClientPlatform: string
{
    case Android = 'android';
    case IOS     = 'ios';
    case Windows = 'windows';
    case Linux   = 'linux';
    case MacOS   = 'macos';
    case Web     = 'web';

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'android'                   => self::Android,
            'ios', 'iphone', 'ipad'     => self::IOS,
            'windows', 'win32', 'win64' => self::Windows,
            'linux'                     => self::Linux,
            'macos', 'darwin', 'osx'    => self::MacOS,
            default                     => self::Web,
        };
    }

    public function isMobile(): bool
    {
        return match ($this) {
            self::Android, self::IOS => true,
            default                  => false,
        };
    }

    public function isDesktop(): bool
    {
        return match ($this) {
            self::Windows, self::Linux, self::MacOS => true,
            default                                 => false,
        };
    }

    public function supportsFirebasePush(): bool
    {
        return match ($this) {
            self::Android, self::IOS => true,
            default                  => false,
        };
    }

    public function supportsLocalNotifications(): bool
    {
        return match ($this) {
            self::Windows, self::Linux, self::MacOS => true,
            default                                 => false,
        };
    }

    public function supportsWebSocket(): bool
    {
        return true; // All platforms support WebSocket via Reverb
    }

    public function supportsFileAttachments(): bool
    {
        return match ($this) {
            self::Web => false,
            default   => true,
        };
    }
}
