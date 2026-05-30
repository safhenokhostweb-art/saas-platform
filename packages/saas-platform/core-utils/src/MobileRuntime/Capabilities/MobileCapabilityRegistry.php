<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Capabilities;

use Illuminate\Support\Facades\Cache;

class MobileCapabilityRegistry
{
    public const CAP_CHAT          = 'chat';
    public const CAP_TICKETS       = 'tickets';
    public const CAP_FORMS         = 'forms';
    public const CAP_NOTIFICATIONS = 'notifications';
    public const CAP_PRESENCE      = 'presence';
    public const CAP_VOICE         = 'voice';
    public const CAP_VIDEO         = 'video';
    public const CAP_FILE_UPLOAD   = 'file_upload';
    public const CAP_ANALYTICS     = 'analytics';
    public const CAP_ADMIN         = 'admin';

    private const PREFIX = 'mobile:capabilities:';
    private const TTL    = 3600;

    /** @var array<string, array> */
    private array $registry = [];

    private array $defaults = [
        self::CAP_CHAT          => ['roles' => ['platform_admin', 'tenant_admin', 'support_agent', 'end_user'], 'description' => 'Real-time chat messaging'],
        self::CAP_TICKETS       => ['roles' => ['platform_admin', 'tenant_admin', 'support_agent', 'end_user'], 'description' => 'Ticket management'],
        self::CAP_FORMS         => ['roles' => ['platform_admin', 'tenant_admin', 'end_user'], 'description' => 'Form submission'],
        self::CAP_NOTIFICATIONS => ['roles' => ['platform_admin', 'tenant_admin', 'support_agent', 'end_user'], 'description' => 'Push notifications'],
        self::CAP_PRESENCE      => ['roles' => ['platform_admin', 'tenant_admin', 'support_agent'], 'description' => 'User presence tracking'],
        self::CAP_VOICE         => ['roles' => [], 'description' => 'Voice messages (future)'],
        self::CAP_VIDEO         => ['roles' => [], 'description' => 'Video calls (future)'],
        self::CAP_FILE_UPLOAD   => ['roles' => ['platform_admin', 'tenant_admin', 'support_agent', 'end_user'], 'description' => 'File attachments'],
        self::CAP_ANALYTICS     => ['roles' => ['platform_admin', 'tenant_admin'], 'description' => 'Analytics dashboard'],
        self::CAP_ADMIN         => ['roles' => ['platform_admin', 'tenant_admin'], 'description' => 'Admin controls'],
    ];

    public function register(string $capability, array $definition): void
    {
        $this->registry[$capability] = $definition;
    }

    public function resolve(string $tenantId, string $role): array
    {
        $cacheKey = self::PREFIX . "resolved:{$tenantId}:{$role}";
        return Cache::remember($cacheKey, self::TTL, function () use ($tenantId, $role) {
            $all     = $this->getAllCapabilities();
            $granted = $this->getGrantedCapabilities($tenantId);
            $result  = [];
            foreach ($all as $cap => $def) {
                $defaultRoles  = $def['roles'] ?? [];
                $grantedRoles  = $granted[$cap]['roles'] ?? $defaultRoles;
                $revoked       = $granted[$cap]['revoked'] ?? false;
                if (! $revoked && (in_array($role, $grantedRoles, true) || in_array('*', $grantedRoles, true))) {
                    $result[$cap] = true;
                } else {
                    $result[$cap] = false;
                }
            }
            return $result;
        });
    }

    public function hasCapability(string $tenantId, string $capability, string $role): bool
    {
        return (bool) ($this->resolve($tenantId, $role)[$capability] ?? false);
    }

    public function grant(string $tenantId, string $capability, array $roles = []): void
    {
        $granted = $this->getGrantedCapabilities($tenantId);
        $granted[$capability] = [
            'roles'   => $roles ?: ['*'],
            'revoked' => false,
            'granted_at' => now()->toIso8601String(),
        ];
        Cache::put(self::PREFIX . "grants:{$tenantId}", $granted, self::TTL);
        $this->clearResolvedCache($tenantId);
    }

    public function revoke(string $tenantId, string $capability, ?string $role = null): void
    {
        $granted = $this->getGrantedCapabilities($tenantId);
        if ($role === null) {
            $granted[$capability] = ['roles' => [], 'revoked' => true];
        } else {
            $existing = $granted[$capability]['roles'] ?? [];
            $granted[$capability]['roles'] = array_values(array_diff($existing, [$role]));
        }
        Cache::put(self::PREFIX . "grants:{$tenantId}", $granted, self::TTL);
        $this->clearResolvedCache($tenantId);
    }

    public function getAllCapabilities(): array
    {
        return array_merge($this->defaults, $this->registry);
    }

    public function getCapabilityDefinition(string $capability): ?array
    {
        return $this->getAllCapabilities()[$capability] ?? null;
    }

    private function getGrantedCapabilities(string $tenantId): array
    {
        return Cache::get(self::PREFIX . "grants:{$tenantId}", []);
    }

    private function clearResolvedCache(string $tenantId): void
    {
        foreach (['platform_admin', 'tenant_admin', 'support_agent', 'end_user'] as $role) {
            Cache::forget(self::PREFIX . "resolved:{$tenantId}:{$role}");
        }
    }
}
