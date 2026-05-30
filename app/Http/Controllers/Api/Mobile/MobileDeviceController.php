<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\MobileDeviceRegistry;
use SaaSPlatform\CoreUtils\MobileRuntime\DeviceManagement\DeviceSecurityValidator;

class MobileDeviceController extends Controller
{
    public function __construct(
        private readonly MobileDeviceRegistry $deviceRegistry,
        private readonly DeviceSecurityValidator $securityValidator,
    ) {}

    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'device_id'    => 'required|string|max:255',
            'platform'     => 'required|in:ios,android',
            'os_version'   => 'required|string',
            'app_version'  => 'required|string',
            'device_model' => 'nullable|string',
            'timezone'     => 'nullable|string',
            'locale'       => 'nullable|string',
        ]);

        $user     = $request->user();
        $tenantId = $this->tenantId($request);

        $validation = $this->securityValidator->validate($request->device_id, $request->all());

        $device = $this->deviceRegistry->register($tenantId, (string) $user->id, $request->only([
            'device_id', 'platform', 'os_version', 'app_version', 'device_model', 'timezone', 'locale',
        ]));

        return response()->json([
            'data' => $device,
            'meta' => ['security' => $validation],
        ], 201);
    }

    public function unregister(Request $request, string $deviceId): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $this->deviceRegistry->unregister($deviceId, $tenantId);
        return response()->json(['data' => ['unregistered' => true]]);
    }

    public function getInfo(Request $request, string $deviceId): JsonResponse
    {
        $device = $this->deviceRegistry->getDevice($deviceId);
        if (! $device) {
            return response()->json(['message' => 'Device not found'], 404);
        }
        return response()->json(['data' => $device]);
    }

    public function updateInfo(Request $request, string $deviceId): JsonResponse
    {
        $this->deviceRegistry->updateDeviceInfo($deviceId, $request->only([
            'os_version', 'app_version', 'timezone', 'locale', 'push_token',
        ]));
        return response()->json(['data' => ['updated' => true]]);
    }

    private function tenantId(Request $request): string
    {
        return (string) ($request->attributes->get('tenant_site')?->id ?? $request->user()?->site_id ?? 'platform');
    }
}
