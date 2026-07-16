<?php

namespace App\Services\CustomerInstallation\Domain\Dto;

/**
 * Phase 15-D — result of a createDomain() attempt. Mirrors SslResult's shape
 * (App\Services\CustomerInstallation\Ssl\Dto\SslResult): $success distinguishes
 * a real failure from a driver where domain creation is not applicable.
 *
 * Unlike SSL, a failed domain creation on cPanel IS a hard pipeline failure
 * (see ProvisionDomainStage — isCapabilityRequiredToProceed()=true) because
 * without a resolved domain/document-root, no later stage (file extraction,
 * .env generation, web server config) can run correctly. failed() therefore
 * exists for completeness but ProvisionDomainStage treats it as fatal, not
 * as an optional-infra warning.
 *
 * $meta carries whatever the provisioner needs to reverse the operation
 * later (e.g. the cPanel "subdomain" prefix used for an addon domain) —
 * opaque to every caller except that same provisioner's removeDomain().
 */
readonly class DomainResult
{
    public function __construct(
        public readonly bool    $success,
        public readonly string  $status,
        public readonly ?string $documentRoot = null,
        public readonly ?string $type = null,
        public readonly ?string $message = null,
        public readonly array   $meta = [],
    ) {}

    public static function created(string $documentRoot, string $type, array $meta = []): self
    {
        return new self(success: true, status: 'created', documentRoot: $documentRoot, type: $type, meta: $meta);
    }

    public static function alreadyExists(?string $documentRoot, ?string $type = null): self
    {
        return new self(success: true, status: 'already_exists', documentRoot: $documentRoot, type: $type);
    }

    /**
     * Domain creation is not an applicable concept for this driver (VPS —
     * see VpsDomainProvisioner). Distinct from alreadyExists() so the
     * ProvisionDomainStage output/logs are honest about which case occurred.
     */
    public static function skippedForDriver(string $message): self
    {
        return new self(success: true, status: 'skipped_success', message: $message);
    }

    public static function failed(string $message): self
    {
        return new self(success: false, status: 'failed', message: $message);
    }

    public function toArray(): array
    {
        return [
            'success'       => $this->success,
            'status'        => $this->status,
            'document_root' => $this->documentRoot,
            'type'          => $this->type,
            'message'       => $this->message,
        ];
    }
}
