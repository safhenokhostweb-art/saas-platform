<?php

namespace App\Services\CustomerInstallation\Dto;

final readonly class InstallabilityResult
{
    /** @param list<string> $reasons */
    public function __construct(
        public bool $installable,
        public array $reasons,
    ) {}

    public static function blocked(array $reasons): self
    {
        return new self(false, array_values(array_unique($reasons)));
    }

    public static function installable(): self
    {
        return new self(true, []);
    }
}