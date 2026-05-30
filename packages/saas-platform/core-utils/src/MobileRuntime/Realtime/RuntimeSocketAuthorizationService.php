<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Realtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class RuntimeSocketAuthorizationService
{
    private const PREFIX  = 'mobile:socket:auth:';
    private const TTL     = 300;

    public function authorizeChannel(string $channelName, int|string $userId, string $tenantId): bool
    {
        $parsed = $this->parseChannelName($channelName);

        return match ($parsed['type']) {
            'presence' => $this->authorizeTenantChannel($parsed['id'] ?? $tenantId, $userId),
            'private'  => $this->authorizePrivateChannel((int) ($parsed['id'] ?? 0), $userId),
            'conversation' => $this->authorizeConversationChannel($parsed['id'] ?? '', $userId, $tenantId),
            default    => false,
        };
    }

    public function authorizeConversationChannel(string $conversationId, int|string $userId, string $tenantId): bool
    {
        // Check conversation participants via cache (set when conversation registered)
        $participantsKey = "mobile:channels:conv:{$conversationId}";
        $channel         = Cache::get($participantsKey);
        if (! $channel) return false;
        return in_array($userId, $channel['participants'] ?? [], false);
    }

    public function authorizeTenantChannel(string $tenantId, int|string $userId, string $requiredRole = 'any'): bool
    {
        // Tenant presence channel — user must belong to tenant
        // The actual membership check happens in the platform middleware
        return ! empty($tenantId) && ! empty($userId);
    }

    public function authorizePrivateChannel(int|string $channelUserId, int|string $requestingUserId): bool
    {
        return (string) $channelUserId === (string) $requestingUserId;
    }

    public function generateAuthToken(string $channelName, int|string $userId, string $tenantId): string
    {
        $payload   = json_encode(['channel' => $channelName, 'user' => $userId, 'tenant' => $tenantId, 'exp' => time() + self::TTL]);
        $secret    = Config::get('app.key', 'secret');
        $token     = base64_encode(hash_hmac('sha256', $payload, $secret, true));
        Cache::put(self::PREFIX . $token, ['channel' => $channelName, 'user' => $userId, 'tenant' => $tenantId], self::TTL);
        return $token;
    }

    public function validateAuthToken(string $token, string $channelName): bool
    {
        $data = Cache::get(self::PREFIX . $token);
        return $data !== null && ($data['channel'] ?? '') === $channelName;
    }

    public function parseChannelName(string $channelName): array
    {
        if (str_starts_with($channelName, 'presence-tenant.')) {
            return ['type' => 'presence', 'id' => str_replace('presence-tenant.', '', $channelName)];
        }
        if (str_starts_with($channelName, 'private-mobile.user.')) {
            return ['type' => 'private', 'id' => str_replace('private-mobile.user.', '', $channelName)];
        }
        if (str_starts_with($channelName, 'presence-conversation.')) {
            return ['type' => 'conversation', 'id' => str_replace('presence-conversation.', '', $channelName)];
        }
        if (str_starts_with($channelName, 'conversation.')) {
            return ['type' => 'conversation', 'id' => str_replace('conversation.', '', $channelName)];
        }
        return ['type' => 'public', 'id' => $channelName];
    }
}
