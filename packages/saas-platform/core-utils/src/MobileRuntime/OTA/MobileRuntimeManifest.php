<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\OTA;

use Carbon\Carbon;

class MobileRuntimeManifest
{
    public readonly string $checksum;
    public readonly Carbon $generatedAt;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $appVersion,
        public readonly string $role,
        public readonly array  $features,
        public readonly array  $navigation,
        public readonly array  $permissions,
        public readonly array  $theme,
        public readonly array  $security,
        public readonly array  $capabilities,
        public readonly array  $updatePolicy,
        ?Carbon $generatedAt = null,
    ) {
        $this->generatedAt = $generatedAt ?? now();
        $this->checksum    = $this->computeChecksum();
    }

    public static function create(string $tenantId, string $appVersion, string $role, array $data): static
    {
        return new static(
            tenantId:     $tenantId,
            appVersion:   $appVersion,
            role:         $role,
            features:     $data['features'] ?? [],
            navigation:   $data['navigation'] ?? [],
            permissions:  $data['permissions'] ?? [],
            theme:        $data['theme'] ?? [],
            security:     $data['security'] ?? [],
            capabilities: $data['capabilities'] ?? [],
            updatePolicy: $data['update_policy'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'schema_version' => '1.0',
            'tenant_id'      => $this->tenantId,
            'app_version'    => $this->appVersion,
            'role'           => $this->role,
            'generated_at'   => $this->generatedAt->toIso8601String(),
            'checksum'       => $this->checksum,
            'features'       => $this->features,
            'navigation'     => $this->navigation,
            'permissions'    => $this->permissions,
            'theme'          => $this->theme,
            'security'       => $this->security,
            'capabilities'   => $this->capabilities,
            'update_policy'  => $this->updatePolicy,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }

    public function isExpired(int $ttlSeconds = 300): bool
    {
        return $this->generatedAt->diffInSeconds(now()) > $ttlSeconds;
    }

    private function computeChecksum(): string
    {
        return hash('sha256', json_encode([
            $this->tenantId,
            $this->appVersion,
            $this->role,
            $this->features,
            $this->theme,
            $this->capabilities,
        ]));
    }
}
