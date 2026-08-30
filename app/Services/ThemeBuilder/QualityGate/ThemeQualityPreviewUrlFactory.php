<?php
namespace App\Services\ThemeBuilder\QualityGate;

use App\Models\Theme;
use Illuminate\Support\Facades\URL;

/**
 * Phase 2g — the one place that turns a Theme into a real, deterministic,
 * short-lived preview target for browser-backed Quality Gate checks.
 *
 * Reuses the exact signed route (admin.theme-builder.canvas.page-preview-signed)
 * CaptureThemeScreenshotsCommand already uses in production: same
 * ThemeCanvasController::preview() render path, same BlockRenderer/
 * GlobalStyleEngine the tenant-facing renderer uses. No new preview
 * infrastructure, no customer data, no fake tenant — access control is the
 * `signed` route middleware (HMAC + expiry) alone.
 */
class ThemeQualityPreviewUrlFactory
{
    private const TTL_MINUTES = 10;
    private const DEFAULT_PAGE_KEY = 'home';

    public function make(Theme $theme): ?string
    {
        $pages = $theme->getPageList();
        if (empty($pages)) {
            return null;
        }

        $pageKey = array_key_exists(self::DEFAULT_PAGE_KEY, $pages)
            ? self::DEFAULT_PAGE_KEY
            : array_key_first($pages);

        return URL::temporarySignedRoute(
            'admin.theme-builder.canvas.page-preview-signed',
            now()->addMinutes(self::TTL_MINUTES),
            ['theme' => $theme->id, 'pageKey' => $pageKey, 'screenshot' => 1]
        );
    }
}
