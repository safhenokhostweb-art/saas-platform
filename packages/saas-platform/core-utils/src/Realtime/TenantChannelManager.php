<?php

namespace SaaSPlatform\CoreUtils\Realtime;

class TenantChannelManager
{
    public function channelFor(string $tenantId, string $scope = 'tenant'): string
    {
        return match($scope) {
            'tenant'     => "tenant.{$tenantId}",
            'forms'      => "tenant.{$tenantId}.forms",
            'tickets'    => "tenant.{$tenantId}.tickets",
            'ai'         => "tenant.{$tenantId}.ai",
            'governance' => "tenant.{$tenantId}.governance",
            'security'   => "tenant.{$tenantId}.security",
            default      => "tenant.{$tenantId}.{$scope}",
        };
    }

    public function userChannelFor(int $userId, string $tenantId): string
    {
        return "user.{$tenantId}.{$userId}";
    }

    public function presenceChannelFor(string $tenantId, string $context): string
    {
        return "presence.{$tenantId}.{$context}";
    }

    public function isAuthorized(string $channel, string $tenantId, int $userId): bool
    {
        // Tenant channels — only same-tenant members
        if (str_starts_with($channel, "tenant.{$tenantId}")) {
            return true;
        }

        // User channels — only the exact user
        if ($channel === "user.{$tenantId}.{$userId}") {
            return true;
        }

        // Presence channels — same tenant
        if (str_starts_with($channel, "presence.{$tenantId}.")) {
            return true;
        }

        return false;
    }

    public function extractTenantId(string $channel): ?string
    {
        if (preg_match('/^(?:tenant|user|presence)\.(\d+)/', $channel, $m)) {
            return $m[1];
        }
        return null;
    }
}
