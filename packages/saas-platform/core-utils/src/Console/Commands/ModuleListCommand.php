<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use SaaSPlatform\CoreUtils\Support\ModuleVersionRegistry;
use SaaSPlatform\CoreUtils\Services\ModuleRegistryService;

/**
 * PHASE 6.98 — TASK 2: module:list
 *
 * Lists all installed canonical modules with version, schema version,
 * compatibility status, source mode, and manifest health.
 */
class ModuleListCommand extends Command
{
    protected $signature = 'module:list
        {--json : Output as JSON}';

    protected $description = 'List all installed canonical modules';

    public function handle(): int
    {
        $registry = app(ModuleVersionRegistry::class);
        $modules  = $registry->all();

        if (empty($modules)) {
            $this->warn('No modules discovered. Check packages/saas-platform/modules/ or vendor/saas-platform/modules-*/.');
            return self::SUCCESS;
        }

        // Determine running platform version (config-driven, fallback to 1.0.0)
        $platformVersion = config('app.platform_version', '1.0.0');

        $rows = [];
        foreach ($modules as $slug => $data) {
            $compatible = $registry->isCompatible($slug, $platformVersion);
            $source     = $data['_source'] ?? 'unknown';
            $sourceIcon = $source === 'local' ? '🔗 local' : '📦 vendor';

            $rows[] = [
                $slug,
                $data['version']        ?? '?',
                $data['schema_version'] ?? '1',
                $compatible ? '✅' : '❌',
                $sourceIcon,
                $data['type']           ?? '?',
                $data['name']           ?? $slug,
            ];
        }

        if ($this->option('json')) {
            $output = [];
            foreach ($modules as $slug => $data) {
                $output[$slug] = [
                    'version'        => $data['version']        ?? null,
                    'schema_version' => $data['schema_version'] ?? '1',
                    'compatible'     => $registry->isCompatible($slug, $platformVersion),
                    'source'         => $data['_source']        ?? 'unknown',
                    'type'           => $data['type']           ?? null,
                    'name'           => $data['name']           ?? $slug,
                    'compatibility'  => $registry->compatibility($slug),
                ];
            }
            $this->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        }

        $this->line('');
        $this->line('📦 Installed Modules (Platform ' . $platformVersion . ')');
        $this->line('');

        $this->table(
            ['Slug', 'Version', 'Schema', 'Compatible', 'Source', 'Type', 'Name'],
            $rows
        );

        $this->line('');
        $this->line('Total: ' . count($modules) . ' module(s).');
        $this->line('Source: 🔗 local = symlink dev path | 📦 vendor = composer production');

        return self::SUCCESS;
    }
}
