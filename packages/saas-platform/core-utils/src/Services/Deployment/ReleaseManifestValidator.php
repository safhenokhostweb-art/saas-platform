<?php

namespace SaaSPlatform\CoreUtils\Services\Deployment;

/**
 * Validates release manifest integrity and completeness.
 * Detects tampering, missing fields, and schema drift.
 */
class ReleaseManifestValidator
{
    private const REQUIRED_FIELDS = [
        'deployment_uuid',
        'environment',
        'released_at',
        'module_versions',
        'migration_batch',
        'checksum',
    ];

    public function validate(array $manifest): array
    {
        $issues = [];

        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($manifest[$field])) {
                $issues[] = "Missing required field: {$field}";
            }
        }

        if (empty($issues)) {
            $issues = array_merge($issues, $this->verifyChecksum($manifest));
        }

        if (!empty($manifest['released_at'])) {
            $issues = array_merge($issues, $this->validateTimestamp($manifest['released_at']));
        }

        return [
            'valid'  => empty($issues),
            'issues' => $issues,
        ];
    }

    public function validateFromFile(string $uuid): array
    {
        $path = storage_path("app/releases/{$uuid}.json");

        if (!file_exists($path)) {
            return ['valid' => false, 'issues' => ["Manifest file not found: {$uuid}.json"]];
        }

        $manifest = json_decode(file_get_contents($path), true);

        if (!is_array($manifest)) {
            return ['valid' => false, 'issues' => ['Manifest is not valid JSON']];
        }

        return $this->validate($manifest);
    }

    private function verifyChecksum(array $manifest): array
    {
        $stored   = $manifest['checksum'] ?? null;
        $computed = hash('sha256', json_encode(
            array_filter($manifest, fn($k) => $k !== 'checksum', ARRAY_FILTER_USE_KEY)
        ));

        if ($stored !== $computed) {
            return ["Checksum mismatch (stored: {$stored}, computed: {$computed}) — manifest integrity compromised"];
        }

        return [];
    }

    private function validateTimestamp(mixed $ts): array
    {
        try {
            $dt = new \DateTimeImmutable($ts);
            if ($dt > new \DateTimeImmutable()) {
                return ['Manifest timestamp is in the future'];
            }
            return [];
        } catch (\Throwable) {
            return ["Invalid timestamp format: {$ts}"];
        }
    }
}
