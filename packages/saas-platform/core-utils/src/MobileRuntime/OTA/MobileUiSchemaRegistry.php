<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\OTA;

use Illuminate\Support\Facades\Cache;

class MobileUiSchemaRegistry
{
    private const PREFIX = 'mobile:ui_schemas:';
    private const TTL    = 3600;

    public function register(string $screenId, array $schema): void
    {
        $all = Cache::get(self::PREFIX . 'global', []);
        $all[$screenId] = array_merge($schema, ['screen_id' => $screenId]);
        Cache::put(self::PREFIX . 'global', $all, self::TTL);
    }

    public function getSchema(string $screenId, string $tenantId, string $role): ?array
    {
        $all    = Cache::get(self::PREFIX . 'global', []);
        $schema = $all[$screenId] ?? null;
        if (! $schema) return null;

        $roleVisibility = $schema['role_visibility'] ?? [];
        if (! empty($roleVisibility) && ! in_array($role, $roleVisibility, true)) {
            return null;
        }
        return $schema;
    }

    public function getScreensForRole(string $tenantId, string $role): array
    {
        $all    = Cache::get(self::PREFIX . 'global', []);
        $result = [];
        foreach ($all as $screenId => $schema) {
            $roleVisibility = $schema['role_visibility'] ?? [];
            if (empty($roleVisibility) || in_array($role, $roleVisibility, true)) {
                $result[$screenId] = $schema;
            }
        }
        return $result;
    }

    public function updateSchema(string $screenId, array $schema): void
    {
        $all = Cache::get(self::PREFIX . 'global', []);
        $all[$screenId] = array_merge($all[$screenId] ?? [], $schema);
        Cache::put(self::PREFIX . 'global', $all, self::TTL);
    }

    public function getNavigationSchema(string $tenantId, string $role): array
    {
        return [
            'type'  => 'stack',
            'role'  => $role,
            'tabs'  => $this->getBottomNavSchema($tenantId, $role),
        ];
    }

    public function getBottomNavSchema(string $tenantId, string $role): array
    {
        return match ($role) {
            'platform_admin' => [
                ['id' => 'home',      'icon' => 'home',     'label' => 'Home'],
                ['id' => 'tenants',   'icon' => 'building', 'label' => 'Tenants'],
                ['id' => 'tickets',   'icon' => 'ticket',   'label' => 'Tickets'],
                ['id' => 'settings',  'icon' => 'cog',      'label' => 'Settings'],
            ],
            'tenant_admin', 'support_agent' => [
                ['id' => 'dashboard', 'icon' => 'home',    'label' => 'Dashboard'],
                ['id' => 'tickets',   'icon' => 'ticket',  'label' => 'Tickets'],
                ['id' => 'chat',      'icon' => 'chat',    'label' => 'Chat'],
                ['id' => 'settings',  'icon' => 'cog',     'label' => 'Settings'],
            ],
            default => [
                ['id' => 'home',     'icon' => 'home',    'label' => 'Home'],
                ['id' => 'support',  'icon' => 'support', 'label' => 'Support'],
                ['id' => 'chat',     'icon' => 'chat',    'label' => 'Chat'],
                ['id' => 'profile',  'icon' => 'user',    'label' => 'Profile'],
            ],
        };
    }

    public function validateSchema(array $schema): bool
    {
        return isset($schema['screen_id']) && isset($schema['components']);
    }

    public function getAllSchemas(): array
    {
        return Cache::get(self::PREFIX . 'global', []);
    }
}
