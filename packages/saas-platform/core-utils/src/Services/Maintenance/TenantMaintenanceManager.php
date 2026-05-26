<?php

namespace SaaSPlatform\CoreUtils\Services\Maintenance;

use Illuminate\Support\Facades\DB;

/**
 * Per-tenant maintenance isolation.
 *
 * One tenant can be put under maintenance while all others remain online.
 * Platform admins always bypass restrictions.
 */
class TenantMaintenanceManager
{
    public function enable(
        int     $siteId,
        string  $scope          = 'full',
        ?string $module         = null,
        ?string $message        = null,
        ?string $deploymentUuid = null,
        string  $initiatedBy    = 'system'
    ): void {
        // Deactivate existing maintenance for this site first
        $this->disable($siteId, $scope);

        DB::table('tenant_maintenance')->insert([
            'site_id'                  => $siteId,
            'scope'                    => $scope,
            'module'                   => $module,
            'message'                  => $message ?? 'سیستم در حال نگهداری است. لطفاً بعداً مراجعه فرمایید.',
            'allow_admin_bypass'       => true,
            'allow_health_bypass'      => true,
            'allow_deployment_bypass'  => true,
            'initiated_by'             => $initiatedBy,
            'deployment_uuid'          => $deploymentUuid,
            'started_at'               => now(),
            'ended_at'                 => null,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);
    }

    public function disable(int $siteId, string $scope = 'full'): void
    {
        DB::table('tenant_maintenance')
            ->where('site_id', $siteId)
            ->where('scope', $scope)
            ->whereNull('ended_at')
            ->update(['ended_at' => now(), 'updated_at' => now()]);
    }

    public function isUnderMaintenance(int $siteId, ?string $module = null): bool
    {
        $query = DB::table('tenant_maintenance')
            ->where('site_id', $siteId)
            ->whereNull('ended_at');

        if ($module) {
            $query->where(function ($q) use ($module) {
                $q->where('scope', 'full')
                  ->orWhere(function ($q2) use ($module) {
                      $q2->where('scope', 'module')->where('module', $module);
                  });
            });
        }

        return $query->exists();
    }

    public function getMaintenanceRecord(int $siteId): ?object
    {
        return DB::table('tenant_maintenance')
            ->where('site_id', $siteId)
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->first();
    }

    public function getStatus(): array
    {
        $active = DB::table('tenant_maintenance')
            ->whereNull('ended_at')
            ->get()
            ->map(fn($r) => (array) $r)
            ->all();

        return [
            'active_count' => count($active),
            'sites'        => $active,
        ];
    }

    public function disableForDeployment(string $deploymentUuid): int
    {
        return DB::table('tenant_maintenance')
            ->where('deployment_uuid', $deploymentUuid)
            ->whereNull('ended_at')
            ->update(['ended_at' => now(), 'updated_at' => now()]);
    }
}
