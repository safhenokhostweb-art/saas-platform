<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\Maintenance\TenantMaintenanceManager;

class TenantDownCommand extends Command
{
    protected $signature = 'tenant:down
                            {site_id           : Site ID to put into maintenance}
                            {--scope=full      : Scope: full, module, partial}
                            {--module=         : Module slug (when scope=module)}
                            {--message=        : Custom maintenance message}
                            {--deployment=     : Deployment UUID associated with this maintenance}';

    protected $description = 'Put a specific tenant into maintenance mode';

    public function handle(TenantMaintenanceManager $manager): int
    {
        $siteId = (int) $this->argument('site_id');

        if ($manager->isUnderMaintenance($siteId)) {
            $this->warn("Site #{$siteId} is already under maintenance.");
            return 0;
        }

        $manager->enable(
            siteId:         $siteId,
            scope:          $this->option('scope'),
            module:         $this->option('module') ?: null,
            message:        $this->option('message') ?: null,
            deploymentUuid: $this->option('deployment') ?: null,
            initiatedBy:    get_current_user() ?: 'artisan'
        );

        $this->info("Site #{$siteId} is now under maintenance (scope: {$this->option('scope')}).");
        return 0;
    }
}
