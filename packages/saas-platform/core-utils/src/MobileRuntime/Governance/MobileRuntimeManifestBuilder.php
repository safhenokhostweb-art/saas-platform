<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Governance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use SaaSPlatform\CoreUtils\MobileRuntime\Capabilities\MobileCapabilityRegistry;
use SaaSPlatform\CoreUtils\MobileRuntime\OTA\MobileThemeRuntime;
use SaaSPlatform\CoreUtils\MobileRuntime\Versioning\MobileVersionPolicyManager;

class MobileRuntimeManifestBuilder
{
    private const PREFIX = 'mobile:manifest:';
    private const TTL    = 300;

    public function __construct(
        private readonly MobileFeatureFlagService $flags,
        private readonly MobileCapabilityRegistry $capabilities,
        private readonly MobileThemeRuntime $theme,
        private readonly MobileVersionPolicyManager $versionPolicy,
    ) {}

    public function build(string $tenantId, string $appVersion, string $role): array
    {
        $cacheKey = $this->getCacheKey($tenantId, $role, $appVersion);
        return Cache::remember($cacheKey, self::TTL, function () use ($tenantId, $appVersion, $role) {
            return [
                'schema_version'  => '1.0',
                'tenant_id'       => $tenantId,
                'role'            => $role,
                'app_version'     => $appVersion,
                'generated_at'    => now()->toIso8601String(),
                'features'        => $this->buildFeatureManifest($tenantId, $role),
                'navigation'      => $this->buildNavigationManifest($tenantId, $role),
                'permissions'     => $this->buildPermissionManifest($tenantId, $role),
                'capabilities'    => $this->buildCapabilityManifest($tenantId),
                'theme'           => $this->buildThemeManifest($tenantId),
                'security'        => $this->buildSecurityManifest($tenantId),
                'ota'             => $this->buildOtaManifest($tenantId, $appVersion),
            ];
        });
    }

    public function buildFeatureManifest(string $tenantId, string $role): array
    {
        $allFlags = $this->flags->getFlags($tenantId);
        $result   = [];
        foreach ($allFlags as $flag => $enabled) {
            if (! str_contains($flag, ':')) {
                $roleKey = "{$flag}:{$role}";
                $result[$flag] = (bool) ($allFlags[$roleKey] ?? $enabled);
            }
        }
        return $result;
    }

    public function buildNavigationManifest(string $tenantId, string $role): array
    {
        $base = match ($role) {
            'platform_admin'  => $this->adminNavigation(),
            'tenant_admin'    => $this->tenantAdminNavigation(),
            'support_agent'   => $this->supportAgentNavigation(),
            default           => $this->endUserNavigation(),
        };
        return $base;
    }

    public function buildPermissionManifest(string $tenantId, string $role): array
    {
        return [
            'can_view_tickets'    => in_array($role, ['platform_admin', 'tenant_admin', 'support_agent']),
            'can_create_tickets'  => in_array($role, ['platform_admin', 'tenant_admin', 'support_agent', 'end_user']),
            'can_manage_tenants'  => $role === 'platform_admin',
            'can_view_analytics'  => in_array($role, ['platform_admin', 'tenant_admin']),
            'can_chat'            => true,
            'can_upload_files'    => $this->flags->isEnabled(MobileFeatureFlagService::FLAG_FILE_UPLOAD, $tenantId, $role),
            'can_use_ai'          => $this->flags->isEnabled(MobileFeatureFlagService::FLAG_AI_ASSISTANT, $tenantId, $role),
        ];
    }

    public function buildCapabilityManifest(string $tenantId): array
    {
        return $this->capabilities->getAllCapabilities();
    }

    public function buildThemeManifest(string $tenantId): array
    {
        return $this->theme->getTheme($tenantId);
    }

    public function buildSecurityManifest(string $tenantId): array
    {
        return [
            'certificate_pinning'   => Config::get('mobile.security.certificate_pinning', false),
            'require_biometric'     => Config::get('mobile.security.require_biometric', false),
            'session_timeout_min'   => Config::get('mobile.security.session_timeout', 60),
            'require_device_pin'    => Config::get('mobile.security.require_device_pin', false),
            'jailbreak_detection'   => Config::get('mobile.security.jailbreak_detection', true),
            'min_tls_version'       => '1.2',
        ];
    }

    public function buildOtaManifest(string $tenantId, string $appVersion): array
    {
        $policy = $this->versionPolicy->getPolicy($tenantId, 'stable');
        return [
            'update_check_interval_min' => 60,
            'current_policy'            => $policy,
            'force_update'              => $this->versionPolicy->isMandatory($appVersion, $tenantId),
            'latest_version'            => $this->versionPolicy->getLatestVersion('stable'),
            'update_url'                => Config::get('mobile.ota_update_url', ''),
        ];
    }

    public function getCacheKey(string $tenantId, string $role, string $appVersion): string
    {
        return self::PREFIX . md5("{$tenantId}:{$role}:{$appVersion}");
    }

    public function invalidateCache(string $tenantId): void
    {
        foreach (['platform_admin', 'tenant_admin', 'support_agent', 'end_user'] as $role) {
            foreach (['1.0.0', '2.0.0', '3.0.0'] as $v) {
                Cache::forget($this->getCacheKey($tenantId, $role, $v));
            }
        }
    }

    private function adminNavigation(): array
    {
        return [
            ['id' => 'dashboard',  'label' => 'Dashboard',  'icon' => 'home',      'route' => '/admin'],
            ['id' => 'tenants',    'label' => 'Tenants',    'icon' => 'building',   'route' => '/admin/tenants'],
            ['id' => 'tickets',    'label' => 'Tickets',    'icon' => 'ticket',     'route' => '/admin/tickets'],
            ['id' => 'analytics',  'label' => 'Analytics',  'icon' => 'chart',      'route' => '/admin/analytics'],
            ['id' => 'settings',   'label' => 'Settings',   'icon' => 'cog',        'route' => '/admin/settings'],
        ];
    }

    private function tenantAdminNavigation(): array
    {
        return [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'home',    'route' => '/dashboard'],
            ['id' => 'tickets',   'label' => 'Tickets',   'icon' => 'ticket',  'route' => '/tickets'],
            ['id' => 'chat',      'label' => 'Chat',      'icon' => 'chat',    'route' => '/chat'],
            ['id' => 'forms',     'label' => 'Forms',     'icon' => 'form',    'route' => '/forms'],
            ['id' => 'settings',  'label' => 'Settings',  'icon' => 'cog',     'route' => '/settings'],
        ];
    }

    private function supportAgentNavigation(): array
    {
        return [
            ['id' => 'tickets', 'label' => 'Tickets', 'icon' => 'ticket', 'route' => '/tickets'],
            ['id' => 'chat',    'label' => 'Chat',    'icon' => 'chat',   'route' => '/chat'],
        ];
    }

    private function endUserNavigation(): array
    {
        return [
            ['id' => 'home',     'label' => 'Home',     'icon' => 'home',    'route' => '/'],
            ['id' => 'tickets',  'label' => 'Support',  'icon' => 'support', 'route' => '/tickets'],
            ['id' => 'chat',     'label' => 'Chat',     'icon' => 'chat',    'route' => '/chat'],
            ['id' => 'profile',  'label' => 'Profile',  'icon' => 'user',    'route' => '/profile'],
        ];
    }
}
