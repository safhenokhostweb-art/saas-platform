<?php

namespace App\Services\CustomerInstallation\Ssl\Dto;

/**
 * Phase 15-B1 — read-only certificate status, returned by
 * SslProvisionerInterface::checkCertificate(). Used by both IssueSslStage
 * (to decide whether issuance is still needed — idempotent resume) and
 * ConfigureWebServerStage (to decide whether the HTTPS server block can be
 * written yet) and PostInstallValidationService::checkSsl() — one source of
 * truth for "is there a real certificate for this domain right now",
 * replacing three independent curl/metadata guesses.
 *
 * Never carries certificate or key content — only metadata (provider,
 * expiry). checkCertificate() implementations must never throw; on any
 * probe failure they return SslStatus::unknown() so a slow/unreachable
 * server never breaks an optional check.
 */
readonly class SslStatus
{
    public function __construct(
        public readonly bool    $active,
        public readonly string  $provider,
        public readonly ?string $expiresAt = null,
        public readonly string  $message = '',
    ) {}

    public static function none(string $message = 'No certificate found'): self
    {
        return new self(active: false, provider: 'none', message: $message);
    }

    public static function unknown(string $message = 'Certificate status could not be verified'): self
    {
        return new self(active: false, provider: 'unknown', message: $message);
    }

    public static function active(string $provider, ?string $expiresAt = null, string $message = ''): self
    {
        return new self(active: true, provider: $provider, expiresAt: $expiresAt, message: $message);
    }

    public function toArray(): array
    {
        return [
            'active'     => $this->active,
            'provider'   => $this->provider,
            'expires_at' => $this->expiresAt,
            'message'    => $this->message,
        ];
    }
}
