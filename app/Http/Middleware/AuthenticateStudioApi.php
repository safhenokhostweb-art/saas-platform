<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates incoming requests from TB-AI Studio local tool.
 * Checks: Bearer API key + optional HMAC signature.
 */
class AuthenticateStudioApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = config('services.studio.api_key', '');

        if (empty($expectedKey)) {
            return response()->json(['message' => 'Studio API not configured on server.'], 503);
        }

        $token = $request->bearerToken();
        if (empty($token) || !hash_equals($expectedKey, $token)) {
            return response()->json(['message' => 'Invalid Studio API key.'], 401);
        }

        // Optional HMAC signature validation (when studio_secret is configured)
        $secret = config('services.studio.secret', '');
        if (!empty($secret)) {
            $sig  = $request->header('X-Studio-Signature', '');
            if (empty($sig)) {
                return response()->json(['message' => 'X-Studio-Signature header required.'], 401);
            }
            // Signature valid for ±1 hour window
            $valid = false;
            foreach ([date('Y-m-d-H'), date('Y-m-d-H', strtotime('-1 hour'))] as $window) {
                if ($request->hasFile('pack')) {
                    $hash     = hash_file('sha256', $request->file('pack')->path());
                    $expected = hash_hmac('sha256', $hash . ':' . $window, $secret);
                    if (hash_equals($expected, $sig)) { $valid = true; break; }
                } else {
                    $valid = true; // non-file requests skip hash check
                }
            }
            if (!$valid) {
                return response()->json(['message' => 'Invalid Studio signature.'], 401);
            }
        }

        return $next($request);
    }
}
