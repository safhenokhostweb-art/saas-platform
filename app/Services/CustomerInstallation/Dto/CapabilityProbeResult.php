<?php

namespace App\Services\CustomerInstallation\Dto;

use App\Services\CustomerInstallation\Enums\DeploymentCapability;
use DateTimeImmutable;

final readonly class CapabilityProbeResult
{
    /**
     * @param list<DeploymentCapability> $available
     * @param list<DeploymentCapability> $failed
     * @param list<array{capability: string, available: bool, checked_at: string, expires_at: string, provider: string, reason: string}> $evidence
     */
    public function __construct(
        public bool $completed,
        public bool $credentialsValidated,
        public array $available,
        public array $failed,
        public ?DateTimeImmutable $probedAt,
        public ?DateTimeImmutable $expiresAt,
        public ?string $reason = null,
        public array $evidence = [],
    ) {}

    public static function pending(string $reason): self
    {
        return new self(false, false, [], [], null, null, $reason);
    }

    public function isStale(?DateTimeImmutable $now = null): bool
    {
        if (!$this->completed) {
            return false;
        }

        return $this->expiresAt === null || $this->expiresAt <= ($now ?? new DateTimeImmutable());
    }

    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }

    /** @param list<DeploymentCapability> $required */
    public function hasAll(array $required): bool
    {
        foreach ($required as $capability) {
            if (!in_array($capability, $this->available, true)) {
                return false;
            }
        }

        return true;
    }

    /** @param list<DeploymentCapability> $required */
    public function hasFreshEvidenceFor(array $required, ?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        foreach ($required as $capability) {
            $matchingEvidence = array_filter(
                $this->evidence,
                static fn (array $item): bool => $item['capability'] === $capability->value
            );

            if ($matchingEvidence === []) {
                return false;
            }

            $item = array_values($matchingEvidence)[0];

            if (!$item['available'] || new DateTimeImmutable($item['expires_at']) <= $now) {
                return false;
            }
        }

        return true;
    }
}