<?php

namespace SaaSPlatform\CoreUtils\SecurityPatchGovernance\Channel;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SecurityPatchChannelManager
{
    private const CHANNELS_CACHE_KEY = 'security.channels.active';
    private const CACHE_TTL          = 3600;

    private static array $defaultChannels = [
        'stable'         => ['priority' => 100, 'trust_level' => 'core',      'license_gated' => false, 'requires_signature' => true,  'allows_rollback' => true,  'rollout_percentage' => 100],
        'security'       => ['priority' => 10,  'trust_level' => 'core',      'license_gated' => false, 'requires_signature' => true,  'allows_rollback' => true,  'rollout_percentage' => 100],
        'emergency'      => ['priority' => 1,   'trust_level' => 'core',      'license_gated' => false, 'requires_signature' => true,  'allows_rollback' => false, 'rollout_percentage' => 100],
        'beta'           => ['priority' => 200, 'trust_level' => 'trusted',   'license_gated' => true,  'requires_signature' => true,  'allows_rollback' => true,  'rollout_percentage' => 10],
        'enterprise'     => ['priority' => 50,  'trust_level' => 'trusted',   'license_gated' => true,  'requires_signature' => true,  'allows_rollback' => true,  'rollout_percentage' => 100],
        'legacy-support' => ['priority' => 150, 'trust_level' => 'sandboxed', 'license_gated' => false, 'requires_signature' => true,  'allows_rollback' => true,  'rollout_percentage' => 100],
    ];

    public function resolve(string $channel): array
    {
        $channels = $this->activeChannels();
        return $channels[$channel] ?? $this->defaultFor($channel);
    }

    public function activeChannels(): array
    {
        return Cache::remember(self::CHANNELS_CACHE_KEY, self::CACHE_TTL, function () {
            if (!$this->tableExists()) {
                return self::$defaultChannels;
            }
            $rows = DB::table('security_patch_channels')->where('active', true)->orderBy('priority')->get();
            if ($rows->isEmpty()) {
                return self::$defaultChannels;
            }
            $result = [];
            foreach ($rows as $row) {
                $result[$row->name] = [
                    'priority'            => $row->priority,
                    'trust_level'         => $row->trust_level,
                    'license_gated'       => (bool) $row->license_gated,
                    'requires_signature'  => (bool) $row->requires_signature,
                    'allows_rollback'     => (bool) $row->allows_rollback,
                    'rollout_percentage'  => $row->rollout_percentage,
                    'configuration'       => json_decode($row->configuration ?? '{}', true),
                ];
            }
            return $result;
        });
    }

    public function isSecurityChannel(string $channel): bool
    {
        return in_array($channel, ['security', 'emergency'], true);
    }

    public function isLicenseGated(string $channel): bool
    {
        $config = $this->resolve($channel);
        return (bool) ($config['license_gated'] ?? false);
    }

    public function requiresSignature(string $channel): bool
    {
        $config = $this->resolve($channel);
        return (bool) ($config['requires_signature'] ?? true);
    }

    public function allowsRollback(string $channel): bool
    {
        $config = $this->resolve($channel);
        return (bool) ($config['allows_rollback'] ?? true);
    }

    public function priorityOf(string $channel): int
    {
        return (int) ($this->resolve($channel)['priority'] ?? 100);
    }

    public function sortedChannels(): array
    {
        $channels = $this->activeChannels();
        uasort($channels, fn($a, $b) => $a['priority'] <=> $b['priority']);
        return $channels;
    }

    public function invalidateCache(): void
    {
        Cache::forget(self::CHANNELS_CACHE_KEY);
    }

    private function defaultFor(string $channel): array
    {
        return self::$defaultChannels[$channel] ?? [
            'priority'           => 100,
            'trust_level'        => 'sandboxed',
            'license_gated'      => false,
            'requires_signature' => true,
            'allows_rollback'    => true,
            'rollout_percentage' => 100,
        ];
    }

    private function tableExists(): bool
    {
        try {
            DB::table('security_patch_channels')->limit(1)->get();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
