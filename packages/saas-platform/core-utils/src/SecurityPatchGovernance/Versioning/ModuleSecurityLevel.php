<?php

namespace SaaSPlatform\CoreUtils\SecurityPatchGovernance\Versioning;

class ModuleSecurityLevel
{
    private function __construct(
        private readonly string $moduleId,
        private readonly string $level,
        private readonly int    $numericLevel,
        private readonly string $moduleVersion
    ) {}

    public static function for(string $moduleId, int $numericLevel, string $moduleVersion): self
    {
        return new self(
            moduleId:      $moduleId,
            level:         sprintf('%s-FSL-%d', strtoupper(substr($moduleId, 0, 3)), $numericLevel),
            numericLevel:  $numericLevel,
            moduleVersion: $moduleVersion
        );
    }

    public static function baseline(string $moduleId): self
    {
        return new self(
            moduleId:      $moduleId,
            level:         strtoupper(substr($moduleId, 0, 3)) . '-FSL-0',
            numericLevel:  0,
            moduleVersion: '0.0.0'
        );
    }

    public function moduleId(): string    { return $this->moduleId; }
    public function level(): string       { return $this->level; }
    public function numeric(): int        { return $this->numericLevel; }
    public function moduleVersion(): string { return $this->moduleVersion; }

    public function satisfies(int $required): bool
    {
        return $this->numericLevel >= $required;
    }

    public function toArray(): array
    {
        return [
            'module_id'      => $this->moduleId,
            'level'          => $this->level,
            'numeric_level'  => $this->numericLevel,
            'module_version' => $this->moduleVersion,
        ];
    }

    public function __toString(): string
    {
        return $this->level;
    }
}
