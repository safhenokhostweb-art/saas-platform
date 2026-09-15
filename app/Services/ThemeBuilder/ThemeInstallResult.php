<?php

namespace App\Services\ThemeBuilder;

/**
 * Plain outcome of ThemeInstallGateway::install() — deliberately not an HTTP
 * response of any kind, so both an HTTP-context caller (ThemeCanvasController,
 * which turns this into a RedirectResponse) and a non-HTTP-context caller
 * (ThemePurchaseOrderObserver, which turns this into a log entry) can share
 * the exact same install call without either one shaping the other's output.
 */
final class ThemeInstallResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?string $errorType,
        public readonly ?string $message,
        public readonly ?int $status = null,
        public readonly array $body = [],
    ) {}

    public static function ok(array $body = []): self
    {
        return new self(true, null, null, 200, $body);
    }

    public static function noDomain(string $message): self
    {
        return new self(false, 'no_domain', $message);
    }

    public static function collision(string $message): self
    {
        return new self(false, 'page_namespace_collision', $message, 409);
    }

    public static function requestFailed(string $message): self
    {
        return new self(false, 'request_failed', $message);
    }

    public static function httpFailed(string $message, int $status, array $body = []): self
    {
        return new self(false, 'http_failed', $message, $status, $body);
    }
}
