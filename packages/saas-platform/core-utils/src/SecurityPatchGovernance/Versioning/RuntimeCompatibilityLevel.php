<?php

namespace SaaSPlatform\CoreUtils\SecurityPatchGovernance\Versioning;

class RuntimeCompatibilityLevel
{
    private function __construct(
        private readonly string $level,
        private readonly int    $numeric,
        private readonly array  $requirements
    ) {}

    public static function current(): self
    {
        return new self(
            level:        'RV-7',
            numeric:      7,
            requirements: [
                'php_min'       => '8.1.0',
                'laravel_min'   => '10.0.0',
                'db_engine'     => 'mysql_8+|pgsql_14+',
                'cache_driver'  => 'redis|file|array',
                'queue_driver'  => 'redis|database|sync',
            ]
        );
    }

    public static function parse(string $level): self
    {
        preg_match('/RV-(\d+)/', $level, $matches);
        return new self(
            level:        $level,
            numeric:      (int) ($matches[1] ?? 0),
            requirements: []
        );
    }

    public function isCompatibleWith(self $required): bool
    {
        return $this->numeric >= $required->numeric;
    }

    public function level(): string  { return $this->level; }
    public function numeric(): int   { return $this->numeric; }
    public function requirements(): array { return $this->requirements; }

    public function checkPhpVersion(): bool
    {
        $min = $this->requirements['php_min'] ?? '8.1.0';
        return version_compare(PHP_VERSION, $min, '>=');
    }

    public function toArray(): array
    {
        return [
            'level'        => $this->level,
            'numeric'      => $this->numeric,
            'requirements' => $this->requirements,
            'php_satisfied' => $this->checkPhpVersion(),
        ];
    }

    public function __toString(): string
    {
        return $this->level;
    }
}
