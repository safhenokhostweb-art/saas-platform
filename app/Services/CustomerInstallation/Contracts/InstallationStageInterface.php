<?php

namespace App\Services\CustomerInstallation\Contracts;

use App\Services\CustomerInstallation\Dto\InstallationContext;
use App\Services\CustomerInstallation\Dto\StageResult;

interface InstallationStageInterface
{
    public function name(): string;

    public function label(): string;

    public function execute(InstallationContext $ctx): StageResult;

    public function rollback(InstallationContext $ctx, array $snapshot): void;

    public function canRollback(): bool;

    public function estimatedSeconds(): int;
}
