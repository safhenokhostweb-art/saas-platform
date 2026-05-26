<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\Maintenance\TenantMaintenanceManager;

class TenantUpCommand extends Command
{
    protected $signature = 'tenant:up
                            {site_id      : Site ID to bring back online}
                            {--scope=full : Scope to restore: full, module, partial}';

    protected $description = 'Bring a specific tenant back online from maintenance mode';

    public function handle(TenantMaintenanceManager $manager): int
    {
        $siteId = (int) $this->argument('site_id');

        if (!$manager->isUnderMaintenance($siteId)) {
            $this->info("Site #{$siteId} is not under maintenance — nothing to do.");
            return 0;
        }

        $manager->disable($siteId, $this->option('scope'));
        $this->info("Site #{$siteId} is back online.");
        return 0;
    }
}
