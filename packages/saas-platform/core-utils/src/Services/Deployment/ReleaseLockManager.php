<?php

namespace SaaSPlatform\CoreUtils\Services\Deployment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Database-backed release lock.
 *
 * Guarantees one active deployment per environment.
 * Supports stale lock expiration and emergency force-unlock.
 */
class ReleaseLockManager
{
    private const DEFAULT_TTL_SECONDS = 3600; // 1 hour

    public function __construct(
        private string $environment = 'production'
    ) {
        $this->environment = config('app.env', 'production');
    }

    public function acquire(string $deploymentUuid, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): bool
    {
        // First expire any stale locks
        $this->expireStale();

        // Check for active lock
        if ($this->hasActiveLock()) {
            return false;
        }

        try {
            DB::table('release_locks')->insert([
                'environment'     => $this->environment,
                'deployment_uuid' => $deploymentUuid,
                'owner'           => $this->owner(),
                'status'          => 'locked',
                'phase'           => DeploymentStateMachine::STATE_PENDING,
                'acquired_at'     => now(),
                'expires_at'      => now()->addSeconds($ttlSeconds),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function release(string $deploymentUuid): bool
    {
        return (bool) DB::table('release_locks')
            ->where('deployment_uuid', $deploymentUuid)
            ->where('environment', $this->environment)
            ->whereIn('status', ['locked'])
            ->update([
                'status'      => 'released',
                'released_at' => now(),
                'updated_at'  => now(),
            ]);
    }

    public function updatePhase(string $deploymentUuid, string $phase): void
    {
        DB::table('release_locks')
            ->where('deployment_uuid', $deploymentUuid)
            ->update(['phase' => $phase, 'updated_at' => now()]);
    }

    public function forceUnlock(string $deploymentUuid): bool
    {
        return (bool) DB::table('release_locks')
            ->where('deployment_uuid', $deploymentUuid)
            ->update([
                'status'      => 'force_released',
                'released_at' => now(),
                'updated_at'  => now(),
            ]);
    }

    public function forceUnlockAll(): int
    {
        return DB::table('release_locks')
            ->where('environment', $this->environment)
            ->where('status', 'locked')
            ->update([
                'status'      => 'force_released',
                'released_at' => now(),
                'updated_at'  => now(),
            ]);
    }

    public function hasActiveLock(): bool
    {
        return DB::table('release_locks')
            ->where('environment', $this->environment)
            ->where('status', 'locked')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function getActiveLock(): ?object
    {
        return DB::table('release_locks')
            ->where('environment', $this->environment)
            ->where('status', 'locked')
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('acquired_at')
            ->first();
    }

    public function history(int $limit = 10): array
    {
        return DB::table('release_locks')
            ->where('environment', $this->environment)
            ->orderByDesc('acquired_at')
            ->limit($limit)
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();
    }

    private function expireStale(): void
    {
        DB::table('release_locks')
            ->where('environment', $this->environment)
            ->where('status', 'locked')
            ->where('expires_at', '<', now())
            ->update([
                'status'      => 'expired',
                'released_at' => now(),
                'updated_at'  => now(),
            ]);
    }

    private function owner(): string
    {
        return gethostname() . ':' . getmypid();
    }
}
