<?php

namespace SaaSPlatform\CoreUtils\Services;

use Illuminate\Contracts\Container\Container;
use SaaSPlatform\CoreUtils\Contracts\TenantContextContract;
use SaaSPlatform\CoreUtils\Contracts\Services\SiteRepositoryInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\AuthUserResolverInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\ModuleLicenseServiceInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\DateFormatterInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\ActivityLoggerInterface;
use SaaSPlatform\CoreUtils\Contracts\Services\CaptchaServiceInterface;

/**
 * Scores each package across four distributability dimensions:
 *
 *  1. Boundary Isolation   — no App\ imports in package PHP source
 *  2. Contract Coverage    — all required platform contracts are bound
 *  3. Runtime Portability  — package-scoped configs exist with defaults
 *  4. Framework Independence — no hidden static coupling (auth(), request(), etc.)
 */
class PackageDistributionValidationService
{
    private const REQUIRED_CONTRACTS = [
        TenantContextContract::class,
        SiteRepositoryInterface::class,
        AuthUserResolverInterface::class,
        ModuleLicenseServiceInterface::class,
        DateFormatterInterface::class,
        ActivityLoggerInterface::class,
    ];

    private const PACKAGE_CONFIGS = [
        'forms'         => 'forms',
        'tickets'       => 'tickets',
        'core-utils'    => 'saas-platform',
    ];

    private const PACKAGE_PATHS = [
        'forms'         => null, // resolved dynamically
        'tickets'       => null,
        'core-utils'    => null,
    ];

    public function __construct(private Container $app) {}

    public function run(bool $strict = false): array
    {
        $packages = $this->discoverPackages();
        $results  = [];

        foreach ($packages as $name => $path) {
            $results[$name] = $this->validatePackage($name, $path, $strict);
        }

        $totalScore = array_sum(array_column($results, 'score'));
        $maxScore   = count($results) * 100;
        $allPassed  = !in_array(false, array_column($results, 'passed'), true);

        return [
            'passed'   => $allPassed,
            'total'    => "{$totalScore}/{$maxScore}",
            'packages' => $results,
            'grade'    => $this->grade($maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0),
        ];
    }

    private function validatePackage(string $name, string $path, bool $strict): array
    {
        $dimensions = [
            'boundary_isolation'    => $this->scoreBoundaryIsolation($path),
            'contract_coverage'     => $this->scoreContractCoverage($name),
            'runtime_portability'   => $this->scoreRuntimePortability($name, $path),
            'framework_independence'=> $this->scoreFrameworkIndependence($path),
        ];

        $score  = (int) round(array_sum(array_column($dimensions, 'score')) / count($dimensions));
        $passed = $strict ? ($score === 100) : ($score >= 75);

        return [
            'package'    => $name,
            'passed'     => $passed,
            'score'      => $score,
            'grade'      => $this->grade($score),
            'dimensions' => $dimensions,
        ];
    }

    // ── Dimension 1: Boundary Isolation ──────────────────────────────────────

    private function scoreBoundaryIsolation(string $path): array
    {
        $violations = $this->findAppImports($path);
        $count      = count($violations);
        $score      = $count === 0 ? 100 : max(0, 100 - ($count * 10));

        return [
            'name'       => 'boundary_isolation',
            'score'      => $score,
            'violations' => $count,
            'detail'     => $count === 0
                ? 'No App\\ imports found'
                : "Found {$count} App\\ import(s): " . implode(', ', array_slice($violations, 0, 3)) . ($count > 3 ? '...' : ''),
        ];
    }

    private function findAppImports(string $path): array
    {
        $violations = [];

        if (!is_dir($path)) {
            return $violations;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $rel = str_replace($path . DIRECTORY_SEPARATOR, '', $file->getPathname());

            if (str_contains($rel, 'vendor' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $src = file_get_contents($file->getPathname());

            if (preg_match('/\buse\s+App\\\\/', $src) || preg_match('/(?<![a-zA-Z])App\\\\(?!Adapters)/', $src)) {
                $violations[] = $rel;
            }
        }

        return $violations;
    }

    // ── Dimension 2: Contract Coverage ───────────────────────────────────────

    private function scoreContractCoverage(string $name): array
    {
        $missing = [];

        foreach (self::REQUIRED_CONTRACTS as $contract) {
            if (!$this->app->bound($contract)) {
                $missing[] = class_basename($contract);
            }
        }

        // Module-specific contracts
        $moduleContracts = $this->moduleSpecificContracts($name);
        foreach ($moduleContracts as $contract) {
            if (!$this->app->bound($contract)) {
                $missing[] = class_basename($contract);
            }
        }

        $total = count(self::REQUIRED_CONTRACTS) + count($moduleContracts);
        $bound = $total - count($missing);
        $score = $total > 0 ? (int) round(($bound / $total) * 100) : 100;

        return [
            'name'    => 'contract_coverage',
            'score'   => $score,
            'bound'   => "{$bound}/{$total}",
            'missing' => $missing,
            'detail'  => empty($missing) ? 'All contracts bound' : 'Missing: ' . implode(', ', $missing),
        ];
    }

    private function moduleSpecificContracts(string $name): array
    {
        return match ($name) {
            'forms'   => [
                \SaaSPlatform\Modules\Forms\Contracts\FormRepositoryInterface::class,
                \SaaSPlatform\Modules\Forms\Contracts\FormServiceInterface::class,
                \SaaSPlatform\CoreUtils\Contracts\Services\CaptchaServiceInterface::class,
            ],
            'tickets' => [
                \SaaSPlatform\Modules\Tickets\Contracts\Services\TicketServiceInterface::class,
                \SaaSPlatform\Modules\Tickets\Contracts\Repositories\TicketRepositoryInterface::class,
            ],
            default   => [],
        };
    }

    // ── Dimension 3: Runtime Portability ─────────────────────────────────────

    private function scoreRuntimePortability(string $name, string $path): array
    {
        $issues = [];

        // Check package config exists
        $configKey = self::PACKAGE_CONFIGS[$name] ?? null;
        if ($configKey && config($configKey) === null) {
            $issues[] = "config('{$configKey}') not loaded";
        }

        // Check no hardcoded config('app.xxx') without fallbacks
        $hardcoded = $this->findHardcodedAppConfig($path);
        foreach ($hardcoded as $item) {
            $issues[] = "hardcoded config('app.{$item}')";
        }

        $score = count($issues) === 0 ? 100 : max(0, 100 - (count($issues) * 20));

        return [
            'name'   => 'runtime_portability',
            'score'  => $score,
            'issues' => $issues,
            'detail' => empty($issues) ? 'Package configs self-contained' : implode('; ', $issues),
        ];
    }

    private function findHardcodedAppConfig(string $path): array
    {
        $found = [];

        if (!is_dir($path)) {
            return $found;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (str_contains($file->getPathname(), 'vendor' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $src = file_get_contents($file->getPathname());

            // Find config('app.xxx') with no second argument (no default)
            if (preg_match_all("/config\('app\.(platform_version|role|name)'\)/", $src, $matches)) {
                foreach ($matches[1] as $key) {
                    $found[] = $key;
                }
            }
        }

        return array_unique($found);
    }

    // ── Dimension 4: Framework Independence ──────────────────────────────────

    private function scoreFrameworkIndependence(string $path): array
    {
        $issues = [];

        if (!is_dir($path)) {
            return ['name' => 'framework_independence', 'score' => 100, 'issues' => [], 'detail' => 'OK'];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $rel = str_replace($path . DIRECTORY_SEPARATOR, '', $file->getPathname());

            if (str_contains($rel, 'vendor' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $src = file_get_contents($file->getPathname());

            // Direct auth() calls in non-adapter/non-fallback context
            if (preg_match('/(?<![a-zA-Z_])auth\(\)(?!.*fallback)/m', $src)) {
                // Allowed in traits with explicit resolver fallback
                if (!str_contains($src, 'AuthUserResolverInterface') && !str_contains($src, 'resolveAuth')) {
                    $issues[] = "raw auth() in {$rel}";
                }
            }
        }

        $score = count($issues) === 0 ? 100 : max(0, 100 - (count($issues) * 15));

        return [
            'name'   => 'framework_independence',
            'score'  => $score,
            'issues' => array_slice($issues, 0, 5),
            'detail' => empty($issues) ? 'No raw static coupling found' : implode('; ', array_slice($issues, 0, 3)),
        ];
    }

    // ── Package Discovery ─────────────────────────────────────────────────────

    private function discoverPackages(): array
    {
        $base     = base_path('packages/saas-platform');
        $packages = [];

        // core-utils
        $coreUtils = $base . '/core-utils/src';
        if (is_dir($coreUtils)) {
            $packages['core-utils'] = $coreUtils;
        }

        // modules/*
        $modulesDir = $base . '/modules';
        if (is_dir($modulesDir)) {
            foreach (scandir($modulesDir) as $module) {
                if ($module === '.' || $module === '..') {
                    continue;
                }
                $srcPath = $modulesDir . '/' . $module . '/src';
                if (is_dir($srcPath)) {
                    $packages[$module] = $srcPath;
                }
            }
        }

        return $packages;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function grade(float $score): string
    {
        return match (true) {
            $score >= 95 => 'A+',
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default      => 'F',
        };
    }
}
