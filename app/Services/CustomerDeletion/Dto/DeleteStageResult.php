<?php

namespace App\Services\CustomerDeletion\Dto;

class DeleteStageResult
{
    private function __construct(
        public readonly bool    $success,
        public readonly bool    $skipped,
        public readonly string  $stage,
        public readonly ?string $error,
        public readonly array   $output,
        public readonly int     $durationMs,
    ) {}

    public static function success(string $stage, array $output = [], int $durationMs = 0): self
    {
        return new self(
            success:    true,
            skipped:    false,
            stage:      $stage,
            error:      null,
            output:     $output,
            durationMs: $durationMs,
        );
    }

    public static function skipped(string $stage, string $reason, int $durationMs = 0): self
    {
        return new self(
            success:    true,
            skipped:    true,
            stage:      $stage,
            error:      null,
            output:     ['reason' => $reason],
            durationMs: $durationMs,
        );
    }

    public static function failure(string $stage, string $error, int $durationMs = 0): self
    {
        return new self(
            success:    false,
            skipped:    false,
            stage:      $stage,
            error:      $error,
            output:     [],
            durationMs: $durationMs,
        );
    }
}
