<?php

namespace SaaSPlatform\ThemeRuntime\Http;

use SaaSPlatform\ThemeRuntime\VendorAssets;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the third-party browser assets vendored in the theme-runtime package
 * (VendorAssets), from the package itself on every host, the same way
 * TemplateLibraryMediaController serves library media.
 *
 * Only whitelisted files are served (an exact lookup, never a path join).
 * The file name carries the upstream version, so responses are immutable.
 */
final class VendorAssetController
{
    public function __invoke(string $file): BinaryFileResponse
    {
        $asset = VendorAssets::file($file) ?? abort(404);

        return response()->file($asset['path'], [
            'Content-Type' => $asset['type'],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
