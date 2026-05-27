<?php

namespace SaaSPlatform\CoreUtils\SecurityPatchGovernance\Channel;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SecurityReleaseRegistry
{
    private const REGISTRY_KEY = 'security.releases.registry';
    private const PENDING_KEY  = 'security.releases.pending';
    private const TTL          = 1800;

    public function register(string $patchId, string $channel, string $severity, array $metadata = []): void
    {
        Cache::forget(self::REGISTRY_KEY);
        Cache::forget(self::PENDING_KEY);

        if (!$this->tableExists()) {
            return;
        }

        DB::table('security_patches')->updateOrInsert(
            ['patch_id' => $patchId],
            array_merge([
                'channel'     => $channel,
                'severity'    => $severity,
                'status'      => 'pending',
                'available_at' => now(),
                'created_at'  => now(),
                'updated_at'  => now(),
            ], $metadata)
        );
    }

    public function pending(string $channel = null): array
    {
        $key = self::PENDING_KEY . ($channel ? ".{$channel}" : '');
        return Cache::remember($key, self::TTL, function () use ($channel) {
            if (!$this->tableExists()) {
                return [];
            }
            $query = DB::table('security_patches')->where('status', 'pending');
            if ($channel) {
                $query->where('channel', $channel);
            }
            return $query->orderByRaw("FIELD(severity,'critical','high','medium','low')")
                         ->get()
                         ->toArray();
        });
    }

    public function critical(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        return DB::table('security_patches')
            ->where('severity', 'critical')
            ->whereIn('status', ['pending', 'staged', 'deploying'])
            ->get()
            ->toArray();
    }

    public function mandatory(): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        return DB::table('security_patches')
            ->where('mandatory', true)
            ->whereIn('status', ['pending', 'staged', 'deploying'])
            ->get()
            ->toArray();
    }

    public function find(string $patchId): ?object
    {
        if (!$this->tableExists()) {
            return null;
        }
        return DB::table('security_patches')->where('patch_id', $patchId)->first();
    }

    public function markDeployed(string $patchId): void
    {
        if (!$this->tableExists()) {
            return;
        }
        DB::table('security_patches')
            ->where('patch_id', $patchId)
            ->update(['status' => 'deployed', 'updated_at' => now()]);
        Cache::forget(self::REGISTRY_KEY);
        Cache::forget(self::PENDING_KEY);
    }

    public function markFailed(string $patchId, string $reason): void
    {
        if (!$this->tableExists()) {
            return;
        }
        DB::table('security_patches')
            ->where('patch_id', $patchId)
            ->update([
                'status'   => 'failed',
                'metadata' => json_encode(['failure_reason' => $reason]),
                'updated_at' => now(),
            ]);
        Cache::forget(self::REGISTRY_KEY);
    }

    public function summary(): array
    {
        if (!$this->tableExists()) {
            return ['available' => false, 'message' => 'registry not initialized'];
        }
        return [
            'total'     => DB::table('security_patches')->count(),
            'pending'   => DB::table('security_patches')->where('status', 'pending')->count(),
            'deployed'  => DB::table('security_patches')->where('status', 'deployed')->count(),
            'failed'    => DB::table('security_patches')->where('status', 'failed')->count(),
            'critical'  => DB::table('security_patches')->where('severity', 'critical')->where('status', 'pending')->count(),
            'mandatory' => DB::table('security_patches')->where('mandatory', true)->where('status', 'pending')->count(),
        ];
    }

    private function tableExists(): bool
    {
        try {
            DB::table('security_patches')->limit(1)->get();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
