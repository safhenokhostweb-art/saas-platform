<?php

namespace SaaSPlatform\ThemeRuntime\Http;

use SaaSPlatform\ThemeRuntime\TemplateLibrary;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Canvas overhaul Unit 5 (ADR-075): serves the template library's media
 * from the package itself, on every host, so a library image never depends
 * on a publish step, a storage symlink or a tenant media row.
 *
 * Only files the library ships are served (an exact whitelist lookup, never
 * a path join), and URLs carry a content hash, so responses are immutable.
 */
final class TemplateLibraryMediaController
{
    public function __invoke(string $file): BinaryFileResponse
    {
        $path = TemplateLibrary::mediaFiles()[$file] ?? abort(404);

        return response()->file($path, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
