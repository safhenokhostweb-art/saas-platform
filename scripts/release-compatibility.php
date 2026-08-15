<?php

declare(strict_types=1);

/**
 * Read-only release preflight for deployment-system artifacts.
 *
 * Usage:
 *   php scripts/release-compatibility.php
 *   php scripts/release-compatibility.php --require-vendor
 *   php scripts/release-compatibility.php --allow-dirty
 */
final class ReleaseCompatibilityValidator
{
    /** @return array{errors: list<string>, packages: list<array{name: string, path: string, composer: bool, locked: bool, vendor: bool}>} */
    public static function validate(string $root, bool $allowDirty, bool $requireVendor): array
    {
        $errors = [];
        foreach (['artisan', 'composer.json', 'composer.lock'] as $artifact) {
            if (!is_file($root . DIRECTORY_SEPARATOR . $artifact)) {
                $errors[] = "Required runtime artifact is missing: {$artifact}";
            }
        }

        $composerPath = $root . DIRECTORY_SEPARATOR . 'composer.json';
        $lockPath = $root . DIRECTORY_SEPARATOR . 'composer.lock';
        if ($errors !== [] || !is_file($composerPath) || !is_file($lockPath)) {
            return ['errors' => $errors, 'packages' => []];
        }

        $composer = self::jsonFile($composerPath, $errors, 'composer.json');
        $lock = self::jsonFile($lockPath, $errors, 'composer.lock');
        if ($composer === null || $lock === null) {
            return ['errors' => $errors, 'packages' => []];
        }

        if (!$allowDirty && self::isGitRepository($root)) {
            $status = trim((string) shell_exec('git -C ' . escapeshellarg($root) . ' status --porcelain'));
            if ($status !== '') {
                $errors[] = 'Release source is dirty. Commit or explicitly isolate the intended revision before creating an artifact.';
            }
        }

        $locked = [];
        foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
            if (is_array($package) && isset($package['name'])) {
                $locked[(string) $package['name']] = true;
            }
        }

        $packages = [];
        foreach ($composer['repositories'] ?? [] as $repository) {
            if (!is_array($repository) || ($repository['type'] ?? null) !== 'path' || empty($repository['url'])) {
                continue;
            }

            $path = self::absolutePath($root, (string) $repository['url']);
            $packageComposer = $path . DIRECTORY_SEPARATOR . 'composer.json';
            if (!is_file($packageComposer)) {
                $errors[] = "Internal path repository is missing composer metadata: {$repository['url']}";
                continue;
            }

            $metadata = self::jsonFile($packageComposer, $errors, $repository['url'] . '/composer.json');
            if ($metadata === null || empty($metadata['name'])) {
                $errors[] = "Internal path repository has no package name: {$repository['url']}";
                continue;
            }
            self::appendPackage($packages, (string) $metadata['name'], $path, true, isset($locked[$metadata['name']]), $root, $requireVendor);
        }

        foreach (($composer['autoload']['psr-4'] ?? []) as $namespace => $path) {
            if (!is_string($path) || !str_starts_with(str_replace('\\', '/', $path), 'packages/')) {
                continue;
            }
            $absolutePath = self::absolutePath($root, $path);
            $packageRoot = self::packageRoot($absolutePath);
            $packageComposer = $packageRoot . DIRECTORY_SEPARATOR . 'composer.json';
            if (!is_file($packageComposer)) {
                $errors[] = "Autoloaded internal package is missing composer metadata: {$path}";
                continue;
            }
            $metadata = self::jsonFile($packageComposer, $errors, $path . '/composer.json');
            if ($metadata !== null && !empty($metadata['name'])) {
                self::appendPackage($packages, (string) $metadata['name'], $packageRoot, false, isset($locked[$metadata['name']]), $root, $requireVendor);
            }
        }

        foreach (($composer['require'] ?? []) as $name => $constraint) {
            if (!str_starts_with((string) $name, 'saas-platform/')) {
                continue;
            }
            if (!isset($locked[$name])) {
                $errors[] = "Composer runtime dependency is absent from composer.lock: {$name}";
            }
        }

        foreach ($packages as $package) {
            if ($package['composer'] && !$package['locked']) {
                $errors[] = "Internal path package is absent from composer.lock: {$package['name']}";
            }
            if ($requireVendor && $package['composer'] && !$package['vendor']) {
                $errors[] = "Release artifact is missing installed package: {$package['name']}";
            }
        }

        return ['errors' => array_values(array_unique($errors)), 'packages' => $packages];
    }

    private static function appendPackage(array &$packages, string $name, string $path, bool $composer, bool $locked, string $root, bool $requireVendor): void
    {
        foreach ($packages as $index => $package) {
            if ($package['name'] === $name) {
                $packages[$index]['composer'] = $package['composer'] || $composer;
                return;
            }
        }
        $packages[] = ['name' => $name, 'path' => $path, 'composer' => $composer, 'locked' => $locked, 'vendor' => is_dir($root . '/vendor/' . $name)];
    }

    private static function jsonFile(string $path, array &$errors, string $label): ?array
    {
        try {
            $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            return is_array($value) ? $value : null;
        } catch (Throwable $exception) {
            $errors[] = "Invalid {$label}: {$exception->getMessage()}";
            return null;
        }
    }

    private static function absolutePath(string $root, string $path): string
    {
        return preg_match('/^[A-Za-z]:[\\\\\/]|^\//', $path) === 1
            ? rtrim($path, '/\\')
            : rtrim($root . DIRECTORY_SEPARATOR . $path, '/\\');
    }

    private static function packageRoot(string $path): string
    {
        if (is_file($path . DIRECTORY_SEPARATOR . 'composer.json')) {
            return $path;
        }
        return dirname($path);
    }

    private static function isGitRepository(string $root): bool
    {
        return is_dir($root . DIRECTORY_SEPARATOR . '.git');
    }
}

$arguments = array_flip(array_slice($argv, 1));
$selfTest = isset($arguments['--self-test']);
if ($selfTest) {
    $fixture = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'release-compatibility-' . bin2hex(random_bytes(6));
    mkdir($fixture . '/packages/demo', 0777, true);
    file_put_contents($fixture . '/artisan', '');
    file_put_contents($fixture . '/composer.json', json_encode([
        'repositories' => [['type' => 'path', 'url' => 'packages/demo']],
        'require' => ['saas-platform/demo' => '@dev'],
    ]));
    file_put_contents($fixture . '/composer.lock', json_encode(['packages' => [['name' => 'saas-platform/demo']]]));
    file_put_contents($fixture . '/packages/demo/composer.json', json_encode(['name' => 'saas-platform/demo']));
    $valid = ReleaseCompatibilityValidator::validate($fixture, true, false);
    unlink($fixture . '/packages/demo/composer.json');
    $missing = ReleaseCompatibilityValidator::validate($fixture, true, false);
    @rmdir($fixture . '/packages/demo'); @rmdir($fixture . '/packages'); @unlink($fixture . '/artisan'); @unlink($fixture . '/composer.json'); @unlink($fixture . '/composer.lock'); @rmdir($fixture);
    if ($valid['errors'] === [] && $missing['errors'] !== []) { echo "[OK] Validator self-test passed.\n"; exit(0); }
    fwrite(STDERR, "[FAIL] Validator self-test failed.\n"); exit(1);
}
$root = dirname(__DIR__);
$result = ReleaseCompatibilityValidator::validate($root, isset($arguments['--allow-dirty']), isset($arguments['--require-vendor']));

foreach ($result['packages'] as $package) {
    printf("%s | source=%s | lock=%s | vendor=%s\n", $package['name'], $package['path'], $package['locked'] ? 'yes' : 'no', $package['vendor'] ? 'yes' : 'no');
}
if ($result['errors'] !== []) {
    foreach ($result['errors'] as $error) {
        fwrite(STDERR, "[FAIL] {$error}\n");
    }
    exit(1);
}
echo "[OK] Release compatibility validation passed.\n";
