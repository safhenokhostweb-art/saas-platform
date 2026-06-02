<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\MobileSessionCoordinator;
use SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\MobileDeviceRegistry;
use SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\DeviceTrustManager;

class MobileAuthController extends Controller
{
    public function __construct(
        private readonly MobileSessionCoordinator $sessionCoordinator,
        private readonly MobileDeviceRegistry $deviceRegistry,
        private readonly DeviceTrustManager $trustManager,
    ) {}

    public function token(Request $request): JsonResponse
    {
        $request->validate([
            'mobile'      => 'required|string|regex:/^09[0-9]{9}$/',
            'password'    => 'required|string',
            'device_id'   => 'required|string',
            'platform'    => 'required|in:ios,android,windows,linux,macos',
            'app_version' => 'required|string',
        ]);

        $user = \App\Models\User::where('mobile', $request->mobile)->first();
        if (! $user || ! \Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            $this->trustManager->recordLoginAttempt($request->device_id, false);
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $tenantId  = (string) ($request->attributes->get('tenant_site')?->id ?? $user->site_id ?? 'platform');
        $this->trustManager->recordLoginAttempt($request->device_id, true);

        // Register device if not already registered
        if (! $this->deviceRegistry->isRegistered($request->device_id)) {
            $this->deviceRegistry->register($tenantId, (string) $user->id, [
                'device_id'    => $request->device_id,
                'platform'     => $request->platform,
                'app_version'  => $request->app_version,
                'os_version'   => $request->os_version ?? 'unknown',
                'device_model' => $request->device_model ?? null,
            ]);
        }

        $session = $this->sessionCoordinator->createSession(
            (string) $user->id,
            $tenantId,
            $request->device_id,
            ['user_agent' => $request->userAgent(), 'ip' => $request->ip()]
        );

        $token = $user->createToken('mobile-' . $request->device_id)->plainTextToken;

        return response()->json([
            'data' => [
                'token'      => $token,
                'session'    => $session,
                'user'       => [
                'id'            => $user->id,
                'name'          => $user->mobile,
                'mobile'        => $user->mobile,
                'is_admin'      => (bool) $user->is_admin,
                'is_site_admin' => (bool) $user->is_site_admin,
                'site_id'       => $user->site_id,
            ],
                'expires_at' => $session['expires_at'],
            ],
            'meta' => ['tenant_id' => $tenantId],
        ]);
    }

    public function refresh(Request $request): JsonResponse
    {
        $request->validate(['session_id' => 'required|string']);
        try {
            $session = $this->sessionCoordinator->refreshSession($request->session_id);
            return response()->json(['data' => $session]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => 'Session not found'], 404);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $request->validate(['session_id' => 'required|string']);
        $this->sessionCoordinator->revokeSession($request->session_id);
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['data' => ['logged_out' => true]]);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $user     = $request->user();
        $tenantId = (string) ($request->attributes->get('tenant_site')?->id ?? $user?->site_id ?? 'platform');
        $count    = $this->sessionCoordinator->revokeAllSessions((string) $user?->id, $tenantId);
        $user?->tokens()->delete();
        return response()->json(['data' => ['revoked_sessions' => $count]]);
    }
}
