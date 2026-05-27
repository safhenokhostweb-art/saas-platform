<?php

namespace SaaSPlatform\CoreUtils\SecurityPatchGovernance\Versioning;

class SecurityPatchLevel
{
    private function __construct(
        private readonly string $level,
        private readonly int    $numericLevel,
        private readonly string $appliedAt,
        private readonly string $patchId
    ) {}

    public static function from(string $patchId, int $numericLevel): self
    {
        return new self(
            level:        sprintf('SPL-%d', $numericLevel),
            numericLevel: $numericLevel,
            appliedAt:    now()->toIso8601String(),
            patchId:      $patchId
        );
    }

    public static function parse(string $levelString): self
    {
        preg_match('/SPL-(\d+)/', $levelString, $matches);
        $numeric = isset($matches[1]) ? (int) $matches[1] : 0;
        return new self(
            level:        $levelString,
            numericLevel: $numeric,
            appliedAt:    now()->toIso8601String(),
            patchId:      'unknown'
        );
    }

    public static function baseline(): self
    {
        return new self(level: 'SPL-0', numericLevel: 0, appliedAt: now()->toIso8601String(), patchId: 'baseline');
    }

    public function isNewerThan(self $other): bool
    {
        return $this->numericLevel > $other->numericLevel;
    }

    public function satisfies(self $requirement): bool
    {
        return $this->numericLevel >= $requirement->numericLevel;
    }

    public function level(): string
    {
        return $this->level;
    }

    public function numeric(): int
    {
        return $this->numericLevel;
    }

    public function patchId(): string
    {
        return $this->patchId;
    }

    public function toArray(): array
    {
        return [
            'level'         => $this->level,
            'numeric_level' => $this->numericLevel,
            'applied_at'    => $this->appliedAt,
            'patch_id'      => $this->patchId,
        ];
    }

    public function __toString(): string
    {
        return $this->level;
    }
}
