<?php

namespace SaaSPlatform\ThemeRuntime\Contracts;

/**
 * Standard version metadata contract for all platform assets (motions,
 * headers, button packs, icon packs). Ensures uniform semantic versioning,
 * change tracking, and immutability across both hosts.
 */
final class AssetVersionMetadata
{
    public const SEMVER_PATTERN = '/^v?(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-9a-zA-Z-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';

    public function __construct(
        public readonly string $version,
        public readonly ?string $changelog = null,
        public readonly ?string $updatedAt = null,
        public readonly int $schemaVersion = 1,
    ) {
        if (!self::isValidVersion($version)) {
            throw new \InvalidArgumentException("Invalid semantic version string [{$version}].");
        }
    }

    public static function isValidVersion(mixed $version): bool
    {
        return is_string($version) && (bool) preg_match(self::SEMVER_PATTERN, trim($version));
    }

    public static function normalizeVersion(string $version): string
    {
        $trimmed = trim($version);
        if (str_starts_with($trimmed, 'v') || str_starts_with($trimmed, 'V')) {
            $trimmed = substr($trimmed, 1);
        }

        return $trimmed;
    }

    /**
     * Compares two semantic version strings ($v1 <=> $v2).
     * Returns -1 if $v1 < $v2, 0 if $v1 == $v2, 1 if $v1 > $v2.
     */
    public static function compare(string $v1, string $v2): int
    {
        return version_compare(self::normalizeVersion($v1), self::normalizeVersion($v2));
    }

    public function isNewerThan(string $otherVersion): bool
    {
        return self::compare($this->version, $otherVersion) > 0;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            version: (string) ($data['version'] ?? '1.0.0'),
            changelog: isset($data['changelog']) ? (string) $data['changelog'] : null,
            updatedAt: isset($data['updated_at']) ? (string) $data['updated_at'] : null,
            schemaVersion: isset($data['schema_version']) ? (int) $data['schema_version'] : 1,
        );
    }

    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'changelog' => $this->changelog,
            'updated_at' => $this->updatedAt,
            'schema_version' => $this->schemaVersion,
        ];
    }
}
