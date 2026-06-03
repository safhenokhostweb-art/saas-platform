<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use SaaSPlatform\CoreUtils\MobileRuntime\Governance\MobileRuntimeManifestBuilder;
use SaaSPlatform\CoreUtils\MobileRuntime\Governance\MobileRuntimeCompatibilityEngine;

class MobileRuntimeManifestController extends Controller
{
    public function __construct(
        private readonly MobileRuntimeManifestBuilder $manifestBuilder,
        private readonly MobileRuntimeCompatibilityEngine $compatibility,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user       = $request->user();
        $appVersion = $request->header('X-App-Version', '1.0.0');
        $tenantId   = (string) ($request->attributes->get('tenant_site')?->id ?? $user?->site_id ?? 'platform');
        $role       = $this->resolveRole($user, $tenantId);

        // Check compatibility first
        $compatibility = $this->compatibility->getCompatibilityReport($appVersion, $tenantId);
        if (! $compatibility['compatible'] && $compatibility['action'] === 'hard_update') {
            return response()->json([
                'data'   => null,
                'meta'   => ['action' => 'hard_update', 'message' => $compatibility['warnings'][0] ?? 'Update required'],
                'errors' => ['version_incompatible' => $compatibility['warnings']],
            ], 426);
        }

        $manifest = $this->manifestBuilder->build($tenantId, $appVersion, $role);

        // Embed user profile so the client can restore role/identity after restart
        $userProfile = [
            'user_id'       => (string) ($user?->id ?? ''),
            'user_name'     => $user?->name ?? '',
            'mobile'        => $user?->mobile ?? '',
            'is_admin'      => (bool) ($user?->is_admin ?? false),
            'is_site_admin' => (bool) ($user?->is_site_admin ?? false),
            'site_id'       => $user?->site_id,
        ];

        return response()->json([
            'data' => array_merge($manifest, $userProfile),
            'meta' => [
                'tenant_id'   => $tenantId,
                'role'        => $role,
                'app_version' => $appVersion,
                'compatibility' => $compatibility,
            ],
        ]);
    }

    private function resolveRole($user, string $tenantId): string
    {
        if (! $user) return 'end_user';
        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) return 'platform_admin';
        if (method_exists($user, 'hasRole')) {
            if ($user->hasRole('support_agent')) return 'support_agent';
            if ($user->hasRole('tenant_admin')) return 'tenant_admin';
        }
        return 'end_user';
    }
}
