<?php

namespace SaaSPlatform\CoreUtils\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Phase 17-G — Part 2/3/7: scaffolds a new module under
 * packages/saas-platform/modules/{slug}/ matching the official contract
 * from module-package-contract.md (Phase 17-F) — a valid module.json,
 * the folder shape module:build/module:validate expect, a minimal
 * ServiceProvider with boot()/register(), and a README.md.
 *
 * Deliberately lives in core-utils, not the host app — it touches no
 * App\Models, only the filesystem, matching module:build/module:list/
 * module:version/module:release's existing location.
 *
 * Never writes to the `modules` DB table — a generated module only
 * becomes a real, deployable module once an admin creates its `modules`
 * row (out of scope here; this command produces source only).
 */
class ModuleCreateCommand extends Command
{
    protected $signature = 'module:create
        {slug : The module slug (lowercase, hyphen-separated, e.g. crm)}
        {--name= : Human-readable module name (defaults to a title-cased slug)}
        {--type=paid : Module type — core|paid|internal (see docs/architecture/module-package-contract.md)}
        {--force : Overwrite an existing module directory}';

    protected $description = 'Scaffold a new module matching the official Module Package Contract';

    protected const VALID_TYPES = ['core', 'paid', 'internal'];

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $type = $this->option('type');

        if (!preg_match('/^[a-z][a-z0-9\-]*$/', $slug)) {
            $this->error("Invalid slug [{$slug}] — must be lowercase, start with a letter, and use hyphens only.");
            return self::FAILURE;
        }

        if (!in_array($type, self::VALID_TYPES, true)) {
            $this->error('Invalid --type [' . $type . '] — expected one of: ' . implode(', ', self::VALID_TYPES));
            return self::FAILURE;
        }

        $modulePath = base_path("packages/saas-platform/modules/{$slug}");

        if (is_dir($modulePath) && !$this->option('force')) {
            $this->error("Module directory already exists: {$modulePath}. Pass --force to overwrite.");
            return self::FAILURE;
        }

        $studly = Str::studly(str_replace('-', '_', $slug));
        $displayName = $this->option('name') ?: Str::title(str_replace('-', ' ', $slug)) . ' Module';
        $namespace = "SaaSPlatform\\Modules\\{$studly}";
        $providerClass = "{$studly}ServiceProvider";
        $providerFqcn = "{$namespace}\\Providers\\{$providerClass}";

        $this->line('');
        $this->line("📦 Scaffolding module: <info>{$slug}</info>");
        $this->line("   Path: {$modulePath}");
        $this->line('');

        $this->makeDirectories($modulePath);
        $this->writeManifest($modulePath, $slug, $displayName, $type, $namespace, $providerFqcn);
        $this->writeComposerJson($modulePath, $slug, $namespace, $providerFqcn);
        $this->writeServiceProvider($modulePath, $namespace, $providerClass, $slug);
        $this->writeRoutesStub($modulePath);
        $this->writeReadme($modulePath, $slug, $displayName);

        $this->info("Module [{$slug}] scaffolded successfully.");
        $this->line('Next steps:');
        $this->line("  1. php artisan module:validate {$slug}");
        $this->line("  2. Implement src/Providers/{$providerClass}.php and your module's code.");
        $this->line("  3. php artisan module:build {$slug}");
        $this->line("  4. php artisan module:release {$slug} 1.0.0");

        return self::SUCCESS;
    }

    private function makeDirectories(string $path): void
    {
        $dirs = [
            'src/Models', 'src/Services', 'src/Contracts', 'src/Providers',
            'database/migrations', 'routes', 'resources/views', 'resources/lang', 'tests',
        ];

        foreach ($dirs as $dir) {
            $full = "{$path}/{$dir}";
            if (!is_dir($full)) {
                mkdir($full, 0755, true);
            }
            // Placeholder so genuinely empty directories are still tracked by git.
            $gitkeep = "{$full}/.gitkeep";
            if (!file_exists($gitkeep) && count(glob("{$full}/*") ?: []) === 0) {
                file_put_contents($gitkeep, '');
            }
        }
    }

    /**
     * Matches ModuleManifestSchema::REQUIRED_FIELDS/RECOMMENDED_FIELDS
     * exactly (Phase 17-F) — a freshly generated module.json must pass
     * module:validate with zero errors and zero warnings out of the box.
     */
    private function writeManifest(string $path, string $slug, string $name, string $type, string $namespace, string $providerFqcn): void
    {
        $manifest = [
            'name' => $name,
            'module_slug' => $slug,
            'version' => '1.0.0',
            'type' => $type,
            'namespace' => $namespace,
            'provider' => $providerFqcn,
            'compatibility' => [
                'php' => '^8.1',
                'laravel' => '^10.0|^11.0',
                'saas-platform' => '>=1.0.0',
            ],
            'dependencies' => [
                'packages' => ['saas-platform/core-utils'],
                'modules' => [],
            ],
            'update_type' => 'minor',
            'install' => [
                'command' => '',
                'publishes' => ["{$slug}-config", "{$slug}-views", "{$slug}-migrations"],
            ],
            'uninstall' => [
                'type' => 'soft',
                'cleanup' => ['tables', 'files', 'permissions'],
            ],
            'license' => 'proprietary',
            'authors' => [['name' => 'SaaS Platform Team']],
        ];

        $this->writeJson("{$path}/module.json", $manifest);
    }

    private function writeComposerJson(string $path, string $slug, string $namespace, string $providerFqcn): void
    {
        $composer = [
            'name' => "saas-platform/modules-{$slug}",
            'description' => "{$slug} module for SaaS Platform",
            'type' => 'library',
            'license' => 'proprietary',
            'require' => [
                'php' => '^8.1',
                'laravel/framework' => '^10.0|^11.0',
                'saas-platform/core-utils' => '@dev',
            ],
            'autoload' => [
                'psr-4' => ["{$namespace}\\" => 'src/'],
            ],
            'extra' => [
                'laravel' => ['providers' => [$providerFqcn]],
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
        ];

        $this->writeJson("{$path}/composer.json", $composer);
    }

    /**
     * Minimal but satisfies module:build's checkProviderRegistration():
     * file exists at the path derived from module.json's `provider`, and
     * contains both boot() and register().
     */
    private function writeServiceProvider(string $path, string $namespace, string $providerClass, string $slug): void
    {
        $content = <<<PHP
        <?php

        namespace {$namespace}\\Providers;

        use Illuminate\\Support\\ServiceProvider;

        class {$providerClass} extends ServiceProvider
        {
            public function register(): void
            {
                //
            }

            public function boot(): void
            {
                \$this->loadRoutesFrom(__DIR__ . '/../../routes/web.php');
                \$this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
                \$this->loadViewsFrom(__DIR__ . '/../../resources/views', '{$slug}');
            }
        }

        PHP;

        file_put_contents("{$path}/src/Providers/{$providerClass}.php", $content);
    }

    private function writeRoutesStub(string $path): void
    {
        $content = <<<'PHP'
        <?php

        use Illuminate\Support\Facades\Route;

        // Register this module's routes here.

        PHP;

        file_put_contents("{$path}/routes/web.php", $content);
    }

    private function writeReadme(string $path, string $slug, string $name): void
    {
        $content = <<<MD
        # {$name}

        - **Slug:** `{$slug}`
        - **Version:** 1.0.0

        ## Installation

        ```
        php artisan module:validate {$slug}
        php artisan module:build {$slug}
        php artisan module:release {$slug} 1.0.0
        ```

        ## Dependencies

        See `module.json` → `dependencies.modules`. None declared by default.

        ## Upgrade Notes

        Document breaking or notable changes here for each released version.

        ## Uninstall Strategy

        See `module.json` → `uninstall`. Default is `soft` (entitlement disabled,
        customer data kept). See docs/architecture/module-package-contract.md
        بخش ۶ for the full soft/hard contract.

        MD;

        file_put_contents("{$path}/README.md", $content);
    }

    private function writeJson(string $path, array $data): void
    {
        file_put_contents(
            $path,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }
}
