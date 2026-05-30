<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Security;

class PatchGovernanceHealthService
{
    public function __construct(
        private readonly SecurityPatchRegistry $registry,
        private readonly MobilePatchRegistryAdapter $adapter,
        private readonly PatchDistributionValidator $distributionValidator,
        private readonly MobileSecurityPatchCoordinator $coordinator,
    ) {}

    public function getHealthScore(): int
    {
        return $this->getHealth()['score'];
    }

    public function getHealth(): array
    {
        $dist    = $this->distributionValidator->validate();
        $sync    = $this->adapter->syncStatus();
        $total   = $this->registry->count();
        $critical = count($this->registry->getCritical());

        $score = $dist['score'];

        return [
            'score'              => $score,
            'grade'              => $this->grade($score),
            'registry_count'     => $total,
            'mobile_count'       => $sync['mobile_count'],
            'critical_count'     => $critical,
            'in_sync'            => $sync['in_sync'],
            'distribution'       => $dist,
            'production_ready'   => $score >= 80 && $sync['in_sync'],
            'generated_at'       => now()->toIso8601String(),
        ];
    }

    public function getChecks(): array
    {
        return $this->distributionValidator->validate()['checks'];
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 97 => 'A+',
            $score >= 93 => 'A',
            $score >= 90 => 'A-',
            $score >= 87 => 'B+',
            $score >= 83 => 'B',
            $score >= 80 => 'B-',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default      => 'F',
        };
    }
}
