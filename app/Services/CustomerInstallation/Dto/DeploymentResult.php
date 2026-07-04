<?php

namespace App\Services\CustomerInstallation\Dto;

final readonly class DeploymentResult
{
    public function __construct(
        public bool $success,
        public string $deploymentId,
        public ?string $driver,
        public mixed $output,
        public array $logs,
        public array $errors,
        public bool $retryScheduled,
        public ?int $retryDelaySeconds,
    ) {}
}
