<?php

namespace App\Services\CustomerInstallation\Ssl\Dto;

/**
 * Phase 15-B1 — result of an issue/renew attempt. Mirrors ProvisioningResult's
 * "never a hard failure for optional infra" shape: $success distinguishes a
 * real failure worth surfacing to the operator from a graceful skip (Arvan-
 * managed domain, certbot missing, AutoSSL unavailable) — IssueSslStage
 * treats both success=false outcomes as an installation WARN, never a FAIL.
 *
 * $certPath / $keyPath are remote filesystem paths only — never certificate
 * or key content. Nothing on this DTO may ever hold PEM/key material; see
 * the Phase 15-B1 security note in VpsSslProvisioner/CpanelSslProvisioner.
 */
readonly class SslResult
{
    public function __construct(
        public readonly bool    $success,
        public readonly string  $status,
        public readonly ?string $certPath = null,
        public readonly ?string $keyPath = null,
        public readonly ?string $message = null,
        public readonly array   $output = [],
    ) {}

    public static function issued(string $certPath, string $keyPath, array $output = []): self
    {
        return new self(success: true, status: 'issued', certPath: $certPath, keyPath: $keyPath, output: $output);
    }

    public static function skipped(string $message): self
    {
        return new self(success: false, status: 'skipped', message: $message);
    }

    public static function failed(string $message, array $output = []): self
    {
        return new self(success: false, status: 'failed', message: $message, output: $output);
    }

    public function toArray(): array
    {
        return [
            'success'   => $this->success,
            'status'    => $this->status,
            'cert_path' => $this->certPath,
            'key_path'  => $this->keyPath,
            'message'   => $this->message,
        ];
    }
}
