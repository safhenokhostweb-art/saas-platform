<?php

namespace App\Services\CustomerInstallation\Dto;

readonly class ValidationResult
{
    public function __construct(
        public readonly bool   $passed,
        public readonly array  $errors   = [],
        public readonly array  $warnings = [],
    ) {}

    public static function pass(array $warnings = []): self
    {
        return new self(passed: true, errors: [], warnings: $warnings);
    }

    public static function fail(array $errors, array $warnings = []): self
    {
        return new self(passed: false, errors: $errors, warnings: $warnings);
    }

    public function firstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    public function toArray(): array
    {
        return [
            'passed'   => $this->passed,
            'errors'   => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
