<?php

namespace SaaSPlatform\ThemeRuntime;

/**
 * Third-party browser assets the theme runtime ships inside the package, so a
 * rendered page never loads code from a public CDN (no supply-chain or
 * availability dependency on unpkg/jsDelivr) and the exact bytes are pinned.
 *
 * Each asset is an exact, versioned copy of an upstream release, recorded
 * with its upstream sha256 (see resources/vendor/<name>/<version>/README.md).
 * It is served by VendorAssetController on both hosts at a versioned,
 * immutable URL, and referenced with a Subresource Integrity hash computed
 * from the served file itself, so the SRI value can never drift from the
 * bytes on disk.
 */
final class VendorAssets
{
    public const ROUTE = 'theme-runtime.vendor';

    /** @lottiefiles/lottie-player — MIT, see resources/vendor/lottie-player/2.0.8/LICENSE. */
    public const LOTTIE_PLAYER = 'lottie-player-2.0.8.js';

    /** Upstream dist/lottie-player.js of the npm tarball @lottiefiles/lottie-player@2.0.8. */
    public const LOTTIE_PLAYER_UPSTREAM_SHA256 = 'f16bdeb47f643a09ec70a37397bf0134c311413395d164744d12cba7098bce49';

    /**
     * Served file name => [path relative to the package root, content type].
     * The controller serves exactly these, never a path join.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const FILES = [
        self::LOTTIE_PLAYER => ['resources/vendor/lottie-player/2.0.8/lottie-player.js', 'application/javascript; charset=utf-8'],
    ];

    /** @var array<string, string> */
    private static array $integrity = [];

    /** @return array{path: string, type: string}|null */
    public static function file(string $name): ?array
    {
        $entry = self::FILES[$name] ?? null;
        if ($entry === null) {
            return null;
        }
        $path = dirname(__DIR__) . '/' . $entry[0];

        return is_file($path) ? ['path' => $path, 'type' => $entry[1]] : null;
    }

    /** Host-relative URL; the file name carries the version, so it is immutable. */
    public static function url(string $name): string
    {
        if (!array_key_exists($name, self::FILES)) {
            throw new \InvalidArgumentException("Unknown theme-runtime vendor asset [{$name}].");
        }

        return '/theme-runtime/vendor/' . rawurlencode($name);
    }

    /** `sha384-…` Subresource Integrity value of the served bytes. */
    public static function integrity(string $name): string
    {
        if (!isset(self::$integrity[$name])) {
            $file = self::file($name) ?? throw new \InvalidArgumentException("Unknown theme-runtime vendor asset [{$name}].");
            self::$integrity[$name] = 'sha384-' . base64_encode((string) hash_file('sha384', $file['path'], true));
        }

        return self::$integrity[$name];
    }

    /** A deferred, integrity-checked `<script>` tag for one vendored asset. */
    public static function scriptTag(string $name): string
    {
        return '<script src="' . htmlspecialchars(self::url($name), ENT_QUOTES) . '" integrity="'
            . htmlspecialchars(self::integrity($name), ENT_QUOTES) . '" defer></script>';
    }
}
