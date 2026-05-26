<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Services\Maintenance\TenantMaintenanceManager;

class TenantMaintenanceStatusCommand extends Command
{
    protected $signature = 'tenant:maintenance-status
                            {--json : Output as JSON}';

    protected $description = 'Show all tenants currently under maintenance';

    public function handle(TenantMaintenanceManager $manager): int
    {
        $status = $manager->getStatus();

        if ($this->option('json')) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>TENANT MAINTENANCE STATUS</>');
        $this->line('  ─────────────────────────────');
        $this->newLine();

        if ($status['active_count'] === 0) {
            $this->line('  <fg=green>✓ All tenants are online</> — no active maintenance windows.');
        } else {
            $this->line("  <fg=yellow>⚠  {$status['active_count']} tenant(s) under maintenance:</>");
            $this->newLine();

            foreach ($status['sites'] as $record) {
                $this->line("  Site #{$record['site_id']}");
                $this->line("     Scope:      {$record['scope']}");
                $this->line("     Since:      {$record['started_at']}");
                $this->line("     Message:    {$record['message']}");
                $this->line("     Initiated:  {$record['initiated_by']}");
                if ($record['deployment_uuid']) {
                    $this->line("     Deployment: {$record['deployment_uuid']}");
                }
                $this->newLine();
            }
        }

        return 0;
    }
}
