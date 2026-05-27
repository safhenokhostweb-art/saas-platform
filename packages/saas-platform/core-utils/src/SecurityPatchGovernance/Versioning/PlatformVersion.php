<?php

namespace SaaSPlatform\CoreUtils\SecurityPatchGovernance\Versioning;

class PlatformVersion
{
    private function __construct(
        private readonly int    $major,
        private readonly int    $minor,
        private readonly int    $patch,
        private readonly string $raw
    ) {}

    public static function parse(string $version): self
    {
        $parts = explode('.', ltrim($version, 'v'));
        return new self(
            major: (int) ($parts[0] ?? 0),
            minor: (int) ($parts[1] ?? 0),
            patch: (int) ($parts[2] ?? 0),
            raw:   $version
        );
    }

    public static function current(): self
    {
        $version = config('saas-platform.version', '1.0.0');
        return self::parse((string) $version);
    }

    public function satisfies(string $constraint): bool
    {
        if (str_starts_with($constraint, '>=')) {
            $required = self::parse(ltrim($constraint, '>= '));
            return $this->isGreaterThanOrEqualTo($required);
        }
        if (str_starts_with($constraint, '>')) {
            $required = self::parse(ltrim($constraint, '> '));
            return $this->isGreaterThan($required);
        }
        return $this->toString() === ltrim($constraint, 'v');
    }

    public function isGreaterThan(self $other): bool
    {
        return version_compare($this->toString(), $other->toString(), '>');
    }

    public function isGreaterThanOrEqualTo(self $other): bool
    {
        return version_compare($this->toString(), $other->toString(), '>=');
    }

    public function major(): int { return $this->major; }
    public function minor(): int { return $this->minor; }
    public function patch(): int { return $this->patch; }

    public function toString(): string
    {
        return "{$this->major}.{$this->minor}.{$this->patch}";
    }

    public function toArray(): array
    {
        return [
            'major'   => $this->major,
            'minor'   => $this->minor,
            'patch'   => $this->patch,
            'version' => $this->toString(),
        ];
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
