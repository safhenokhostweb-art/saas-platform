<?php

namespace App\Services\CustomerInstallation\Dto;

class CommandResult
{
    public function __construct(
        public readonly int    $exitCode,
        public readonly string $stdout,
        public readonly string $stderr,
        public readonly int    $durationMs,
    ) {}

    public function succeeded(): bool
    {
        return $this->exitCode === 0;
    }

    public function failed(): bool
    {
        return $this->exitCode !== 0;
    }

    public function output(): string
    {
        return trim($this->stdout . ($this->stderr ? "\n" . $this->stderr : ''));
    }

    public function toArray(): array
    {
        return [
            'exit_code'   => $this->exitCode,
            'stdout'      => $this->stdout,
            'stderr'      => $this->stderr,
            'duration_ms' => $this->durationMs,
        ];
    }
}
