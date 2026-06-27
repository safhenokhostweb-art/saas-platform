<?php

namespace App\Services\CustomerInstallation\Dto;

class StageResult
{
    public function __construct(
        public readonly bool    $success,
        public readonly string  $stage,
        public readonly array   $output,
        public readonly int     $durationMs,
        public readonly array   $rollbackSnapshot = [],
        public readonly ?string $error = null,
        public readonly bool    $skipped = false,
    ) {}

    public static function success(string $stage, array $output, array $rollbackSnapshot = [], int $durationMs = 0): self
    {
        return new self(
            success:          true,
            stage:            $stage,
            output:           $output,
            durationMs:       $durationMs,
            rollbackSnapshot: $rollbackSnapshot,
        );
    }

    public static function failure(string $stage, string $error, int $durationMs = 0): self
    {
        return new self(
            success:    false,
            stage:      $stage,
            output:     [],
            durationMs: $durationMs,
            error:      $error,
        );
    }

    public static function skipped(string $stage, string $reason = ''): self
    {
        return new self(
            success: true,
            stage:   $stage,
            output:  ['reason' => $reason],
            durationMs: 0,
            skipped: true,
        );
    }
}
