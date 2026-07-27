<?php

namespace App\Modules\MotionGraphics\DTO;

/**
 * Immutable validation outcome for manifest/package/projection checks.
 * `errors` entries are stable {code, message} pairs — never raw
 * exceptions or internal stack detail.
 */
class ValidationReport
{
    /**
     * @param list<array{code: string, message: string}> $errors
     */
    private function __construct(
        public readonly bool $valid,
        public readonly array $errors = [],
        public readonly string $reportVersion = '1.0',
    ) {}

    public static function pass(): self
    {
        return new self(true);
    }

    /**
     * @param list<array{code: string, message: string}> $errors
     */
    public static function fail(array $errors): self
    {
        return new self(false, $errors);
    }
}
