<?php

namespace Tests\Unit\ThemeBuilder;

use SaaSPlatform\ThemeRuntime\VendorAssets;
use Tests\TestCase;

/**
 * Third-party browser code ships inside the theme-runtime package, pinned to
 * an exact upstream release, served by the package on both hosts and
 * referenced with Subresource Integrity, never from a public CDN.
 */
final class VendorAssetsTest extends TestCase
{
    private const PACKAGE = 'packages/saas-platform/modules/theme-runtime';

    public function test_the_vendored_lottie_player_is_the_exact_pinned_upstream_release(): void
    {
        $file = VendorAssets::file(VendorAssets::LOTTIE_PLAYER);

        $this->assertNotNull($file);
        $this->assertSame(VendorAssets::LOTTIE_PLAYER_UPSTREAM_SHA256, hash_file('sha256', $file['path']), 'the vendored copy must never be edited in place');
        $dir = dirname($file['path']);
        $this->assertStringContainsString('MIT License', (string) file_get_contents($dir.'/LICENSE'));
        $this->assertStringContainsString('Copyright (c) 2019 LottieFiles.com', (string) file_get_contents($dir.'/LICENSE'));
        $this->assertStringContainsString(VendorAssets::LOTTIE_PLAYER_UPSTREAM_SHA256, (string) file_get_contents($dir.'/README.md'));
    }

    public function test_the_script_tag_is_local_versioned_and_integrity_checked(): void
    {
        $file = VendorAssets::file(VendorAssets::LOTTIE_PLAYER);
        $sri = 'sha384-'.base64_encode(hash_file('sha384', $file['path'], true));

        $this->assertSame($sri, VendorAssets::integrity(VendorAssets::LOTTIE_PLAYER));
        $this->assertSame(
            '<script src="/theme-runtime/vendor/lottie-player-2.0.8.js" integrity="'.$sri.'" defer></script>',
            VendorAssets::scriptTag(VendorAssets::LOTTIE_PLAYER)
        );
    }

    public function test_unknown_assets_are_refused(): void
    {
        $this->assertNull(VendorAssets::file('lottie-player-latest.js'));
        $this->expectException(\InvalidArgumentException::class);
        VendorAssets::url('../../.env');
    }

    public function test_the_package_route_serves_it_immutably_and_nothing_else(): void
    {
        $response = $this->get(VendorAssets::url(VendorAssets::LOTTIE_PLAYER));

        $response->assertOk();
        $this->assertStringStartsWith('application/javascript', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertNull($response->headers->get('Set-Cookie'), 'a static asset must not start a session');
        $this->assertSame(VendorAssets::LOTTIE_PLAYER_UPSTREAM_SHA256, hash_file('sha256', $response->baseResponse->getFile()->getPathname()));

        $this->get('/theme-runtime/vendor/lottie-player-2.0.9.js')->assertNotFound();
        $this->get('/theme-runtime/vendor/..%2F..%2Fcomposer.json')->assertNotFound();
        $this->get('/theme-runtime/vendor/README.md')->assertNotFound();
    }

    public function test_no_theme_runtime_source_loads_code_from_a_public_cdn(): void
    {
        $roots = ['src', 'resources/views', 'resources/js', 'resources/library'];
        $offenders = [];
        foreach ($roots as $root) {
            $dir = base_path(self::PACKAGE.'/'.$root);
            if (! is_dir($dir)) {
                continue;
            }
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (! in_array($file->getExtension(), ['php', 'js', 'json', 'css'], true) || $file->getFilename() === 'VendorAssets.php') {
                    continue;
                }
                $code = (string) file_get_contents($file->getPathname());
                if (preg_match('#<script[^>]+src=["\']https?://|https?://(unpkg\.com|cdn\.jsdelivr\.net|cdnjs\.cloudflare\.com)/[^"\']*\.js#i', $code) === 1) {
                    $offenders[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $offenders, 'load third-party browser code through VendorAssets instead');
    }
}
