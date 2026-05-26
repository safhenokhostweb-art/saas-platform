<?php

namespace SaaSPlatform\CoreUtils\PluginGovernance\Trust;

class PluginIntegrityValidator
{
    public function computeHash(string $modulePath): ?string
    {
        if (!is_dir($modulePath)) {
            return null;
        }

        $files = glob($modulePath . '/**/*.php', GLOB_BRACE) ?: [];
        sort($files);

        if (empty($files)) {
            return null;
        }

        $hash = hash_init('sha256');

        foreach ($files as $file) {
            hash_update($hash, $file);
            hash_update_file($hash, $file);
        }

        return hash_final($hash);
    }

    public function computeSourceHash(string $source): string
    {
        return hash('sha256', $source);
    }

    public function verifyHash(string $modulePath, string $expectedHash): bool
    {
        $actual = $this->computeHash($modulePath);

        if ($actual === null) {
            return false;
        }

        return hash_equals($expectedHash, $actual);
    }

    public function verifySourceHash(string $source, string $expectedHash): bool
    {
        return hash_equals($expectedHash, $this->computeSourceHash($source));
    }
}
