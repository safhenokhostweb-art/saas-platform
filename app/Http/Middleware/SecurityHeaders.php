<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PHASE 8.0 — HTTP security headers for all web responses.
 *
 * Headers applied:
 *   X-Frame-Options            — Prevents clickjacking
 *   X-Content-Type-Options     — Prevents MIME sniffing
 *   Referrer-Policy            — Limits referrer leakage
 *   Permissions-Policy         — Restricts browser APIs
 *   Strict-Transport-Security  — Forces HTTPS (when FORCE_HTTPS=true)
 *   Content-Security-Policy    — Restrictive policy; adjust per-feature
 *   X-XSS-Protection           — Legacy XSS filter (belt + suspenders)
 *
 * CSP is report-only in non-production to avoid breakage on new UI additions.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip for binary/streaming responses to avoid corruption
        if ($this->isBinaryResponse($response)) {
            return $response;
        }

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        if (config('app.force_https', false)) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // CSP: report-only outside production to catch violations without breaking pages
        $csp      = $this->buildCsp();
        $cspHeader = app()->isProduction() ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only';
        $response->headers->set($cspHeader, $csp);

        return $response;
    }

    private function buildCsp(): string
    {
        $appUrl = rtrim(config('app.url', ''), '/');

        // 'unsafe-eval' is retained deliberately and is NOT removable while
        // Alpine.js drives the UI: Alpine compiles x-* attribute expressions
        // with new Function(), which no browser can do without it. Removing it
        // would break every interactive screen, so the trade-off is accepted
        // and documented rather than silently inherited.
        //
        // No third-party CDN origin is allowed here. Scripts must be same-origin
        // (bundled by Vite) or the platform's own {$appUrl}. The Canvas editor
        // used to pull SortableJS from cdn.jsdelivr.net, which this policy
        // correctly blocked; that dependency is already bundled by Vite
        // (resources/js/app.js exposes window.Sortable), so the CDN tag was
        // removed rather than this policy being widened.
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$appUrl}",
            "style-src 'self' 'unsafe-inline' {$appUrl} https://fonts.googleapis.com",
            "img-src 'self' data: blob: {$appUrl}",
            "font-src 'self' data: {$appUrl} https://fonts.gstatic.com",
            "connect-src 'self' {$appUrl}",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }

    private function isBinaryResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        // Do not add HTML security headers to file downloads or streams
        return !str_contains($contentType, 'text/html')
            && !str_contains($contentType, 'application/json')
            && !str_contains($contentType, 'text/plain')
            && $contentType !== '';
    }
}
