<?php

namespace Tests\Feature\Infrastructure;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Commercial Productization fase-4 — a real, confirmed bug found via an
 * actual local end-to-end theme install (not a mocked test): the
 * `storage/{path}` fallback route (routes/web.php, used locally when the
 * `public/storage` symlink 404s) had no video/audio extensions in its MIME
 * map at all, so this theme's own real local demo video (hero-intro.webm)
 * was served as `application/octet-stream` — which customer-site's
 * ThemeMediaLocalizationService correctly rejects as an untrusted content
 * type, so the real install failed until this was fixed.
 */
class StorageFallbackRouteVideoMimeTest extends TestCase
{
    public function test_webm_file_is_served_with_the_real_video_content_type(): void
    {
        Storage::disk('public')->put('theme-media/test/sample.webm', 'fake-bytes-not-a-real-video');

        $response = $this->get('/storage/theme-media/test/sample.webm');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'video/webm');
    }

    public function test_mp4_file_is_served_with_the_real_video_content_type(): void
    {
        Storage::disk('public')->put('theme-media/test/sample.mp4', 'fake-bytes-not-a-real-video');

        $response = $this->get('/storage/theme-media/test/sample.mp4');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'video/mp4');
    }

    public function test_image_extensions_are_unaffected(): void
    {
        Storage::disk('public')->put('theme-media/test/sample.png', 'fake-bytes-not-a-real-image');

        $response = $this->get('/storage/theme-media/test/sample.png');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
    }
}
