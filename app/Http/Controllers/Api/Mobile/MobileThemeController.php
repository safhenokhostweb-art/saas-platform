<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\ThemeEngine\ThemeTokenRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Theme Engine API for Flutter (mobile) and Electron (desktop) clients.
 * Called on app launch and after login to sync the active theme.
 *
 * GET /api/mobile/theme/tokens  — structured JSON payload (Flutter-friendly keys)
 * GET /api/mobile/theme/css-vars — raw CSS var map for web-view rendering
 */
class MobileThemeController extends Controller
{
    public function __construct(private ThemeTokenRenderer $renderer) {}

    /**
     * Structured theme payload for Flutter / Electron clients.
     * Colors are resolved for the authenticated user's tenant context.
     */
    public function tokens(Request $request): JsonResponse
    {
        $siteId = $this->resolveSiteId($request);
        return response()->json([
            'success' => true,
            'data'    => $this->renderer->mobilePayload($siteId),
        ]);
    }

    /**
     * Raw CSS custom property map — useful for web-view themes.
     */
    public function cssVars(Request $request): JsonResponse
    {
        $siteId  = $this->resolveSiteId($request);
        $profile = app(\App\Services\ThemeEngine\ThemeProfileManager::class)->resolve($siteId);
        $vars    = $profile ? $profile->toCssVars() : [];

        return response()->json([
            'success'    => true,
            'theme_name' => $profile?->name ?? 'Enterprise Blue',
            'preset_key' => $profile?->preset_key ?? 'enterprise-blue',
            'css_vars'   => $vars,
        ]);
    }

    private function resolveSiteId(Request $request): ?int
    {
        // If the request carries tenant context (e.g. X-Site-Id header), use it.
        $header = $request->header('X-Site-Id');
        if ($header && is_numeric($header)) {
            return (int) $header;
        }
        return null;
    }
}
