<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\SecurityCenter;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\SecurityCenter\SecurityPatchCoordinator;
use SaaSPlatform\CoreUtils\SecurityCenter\SecurityUpdateChannelResolver;

class ListPatchesCommand extends Command
{
    protected $signature = 'security-center:list-patches
        {--tenant=global : Tenant ID}
        {--channel= : Filter by channel (security|stability|feature)}
        {--installed : Show installed patches instead of pending}
        {--json : Output as JSON}';

    protected $description = 'List available, pending, or installed security patches with channel and severity information';

    public function handle(
        SecurityPatchCoordinator $coordinator,
        SecurityUpdateChannelResolver $channelResolver
    ): int {
        $tenantId  = $this->option('tenant');
        $channel   = $this->option('channel');
        $installed = $this->option('installed');

        $patches = $installed
            ? $coordinator->getInstalledPatches($tenantId)
            : $coordinator->getPendingPatches($tenantId);

        if ($channel) {
            $patches = array_filter($patches, fn ($p) => ($p['channel'] ?? 'stability') === $channel);
        }

        if ($this->option('json')) {
            $this->line(json_encode(array_values($patches), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        $type = $installed ? 'Installed' : 'Pending';
        $this->newLine();
        $this->line("  <fg=cyan;options=bold>Security Center — {$type} Patches</> (tenant: {$tenantId})");
        $this->line('  ' . str_repeat('─', 70));

        if (empty($patches)) {
            $this->line("  <fg=gray>No {$type} patches found.</>");
            $this->newLine();
            return 0;
        }

        $this->newLine();
        foreach (array_values($patches) as $patch) {
            $sevColor = match($patch['severity'] ?? 'medium') {
                'critical' => 'red', 'high' => 'yellow', 'medium' => 'blue', default => 'gray'
            };
            $chanIcon = match($patch['channel'] ?? 'stability') {
                'security', 'critical', 'hotfix' => '🔒', 'feature' => '✨', default => '🔧'
            };
            $id       = str_pad($patch['id'] ?? '', 20);
            $title    = str_pad($patch['title'] ?? 'Untitled', 30);
            $ver      = $patch['version'] ?? '?';
            $sev      = "<fg={$sevColor}>" . str_pad(strtoupper($patch['severity'] ?? 'medium'), 8) . "</>";
            $ch       = $patch['channel'] ?? 'stability';

            $this->line("  {$chanIcon}  {$id} {$title} v{$ver}  {$sev}  [{$ch}]");
        }

        $this->newLine();
        $summary = $coordinator->getPatchSummary($tenantId);
        $this->line("  Total: " . count($patches) . "  Critical Pending: {$summary['pending_critical']}  Confidence: {$summary['confidence']}%");
        $this->newLine();
        return 0;
    }
}
