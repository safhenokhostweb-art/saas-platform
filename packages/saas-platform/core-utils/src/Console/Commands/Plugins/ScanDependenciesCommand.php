<?php

namespace SaaSPlatform\CoreUtils\Console\Commands\Plugins;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning\PluginDependencyScanner;
use SaaSPlatform\CoreUtils\PluginGovernance\DependencyScanning\DependencyRiskClassifier;

class ScanDependenciesCommand extends Command
{
    protected $signature   = 'plugins:scan-dependencies {path : Path to module directory} {--module=unknown : Module ID}';
    protected $description = 'Scan a plugin directory for unsafe imports and dependency risks';

    public function handle(PluginDependencyScanner $scanner): int
    {
        $path     = $this->argument('path');
        $moduleId = $this->option('module');

        $this->info("Scanning [{$moduleId}] at: {$path}");
        $this->newLine();

        $report = $scanner->scan($moduleId, $path);

        $classification = $report['overall_classification'] ?? DependencyRiskClassifier::BLOCKED;
        $color          = match($classification) {
            DependencyRiskClassifier::SAFE    => 'green',
            DependencyRiskClassifier::WARNING => 'yellow',
            DependencyRiskClassifier::UNSAFE  => 'red',
            DependencyRiskClassifier::BLOCKED => 'red',
            default                           => 'white',
        };

        $this->line("Files scanned  : " . ($report['scanned_files'] ?? 0));
        $this->line("Issues found   : " . ($report['issues_found'] ?? 0));
        $this->line("Classification : <fg={$color}>{$classification}</>");
        $this->newLine();

        if (!empty($report['issues'])) {
            $this->warn('Issues:');
            foreach ($report['issues'] as $issue) {
                $this->line("  [{$issue['classification']}] {$issue['file']}");
                foreach ($issue['findings'] as $finding) {
                    $this->line("    - [{$finding['severity']}] {$finding['pattern']}: {$finding['reason']}");
                }
            }
        }

        $allowed = in_array($classification, [DependencyRiskClassifier::SAFE, DependencyRiskClassifier::WARNING], true);

        if ($allowed) {
            $this->info('Module is safe for sandbox execution.');
        } else {
            $this->error('Module BLOCKED — unsafe patterns detected.');
        }

        return $allowed ? self::SUCCESS : self::FAILURE;
    }
}
