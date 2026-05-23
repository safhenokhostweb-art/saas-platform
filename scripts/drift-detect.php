<?php
/**
 * PHASE 5 — ANTI-DRIFT DETECTION ENGINE
 *
 * Lightweight executable enforcement layer.
 * Detects: duplicate Blade files, duplicate controllers, shared UI violations,
 *          package ownership violations, namespace conflicts.
 *
 * Usage:
 *   php scripts/drift-detect.php [--ci] [--report] [--fix]
 *   php scripts/drift-detect.php --check-blade
 *   php scripts/drift-detect.php --check-controllers
 *   php scripts/drift-detect.php --check-ownership
 *   php scripts/drift-detect.php --check-contracts
 *   php scripts/drift-detect.php --report
 */

declare(strict_types=1);

// ─── Configuration ───────────────────────────────────────────────────────────

define('SAAS_ROOT', dirname(__DIR__));
define('CUSTOMER_ROOT', dirname(SAAS_ROOT) . DIRECTORY_SEPARATOR . 'customer-site');

define('SIMILARITY_THRESHOLD', 0.85); // 85% similarity = near-identical
define('MAX_REPORT_AGE_DAYS', 7);

// Whitelist: files that are ALLOWED to be duplicated (shared modules)
define('WHITELIST_FILE', SAAS_ROOT . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . '.drift-whitelist.json');

// Shared ownership map: which directories belong to which owner
$SHARED_OWNERSHIP = [
    // Module directories that should NOT be duplicated
    'resources/views/admin/forms' => 'shared:forms',
    'resources/views/admin/menus' => 'shared:menus',
    'resources/views/admin/pages' => 'shared:pages',
    'resources/views/admin/captcha' => 'shared:captcha',
    'resources/views/admin/activity-log' => 'shared:activity-log',
    'resources/views/components' => 'shared:ui-kit',
    'app/Http/Controllers/Admin/FormController.php' => 'shared:forms',
    'app/Http/Controllers/Admin/MenuController.php' => 'shared:menus',
    'app/Http/Controllers/Admin/PageController.php' => 'shared:pages',
];

// ─── Helpers ─────────────────────────────────────────────────────────────────

function e(string $s): string { return $s; }
function writeln(string $msg, string $color = ''): void {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'cyan' => "\033[36m",
        'bold' => "\033[1m",
        'reset' => "\033[0m",
    ];
    $prefix = $colors[$color] ?? '';
    $suffix = $prefix ? $colors['reset'] : '';
    fwrite(STDOUT, $prefix . $msg . $suffix . PHP_EOL);
}

function loadWhitelist(): array {
    if (!file_exists(WHITELIST_FILE)) {
        return [];
    }
    $data = json_decode(file_get_contents(WHITELIST_FILE), true);
    return $data['files'] ?? [];
}

function isWhitelisted(string $relativePath): bool {
    $whitelist = loadWhitelist();
    // Normalize path separators (Windows uses \ but whitelist uses /)
    $relativePath = str_replace('\\', '/', $relativePath);
    foreach ($whitelist as $entry) {
        if ($entry === $relativePath) return true;
        // Support wildcard: "admin/forms/*"
        if (str_ends_with($entry, '/*') && str_starts_with($relativePath, rtrim($entry, '*'))) return true;
    }
    return false;
}

function getRelativePath(string $fullPath, string $root): string {
    return ltrim(str_replace($root, '', $fullPath), DIRECTORY_SEPARATOR);
}

function findAllBladeFiles(string $root): array {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views', FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php' && str_ends_with($file->getFilename(), '.blade.php')) {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

function findAllControllerFiles(string $root): array {
    $files = [];
    $controllerDir = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers';
    if (!is_dir($controllerDir)) return $files;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($controllerDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    sort($files);
    return $files;
}

function normalizeBladeContent(string $content): string {
    // Remove whitespace, comments, and variable-specific content for comparison
    $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);
    $content = preg_replace('/\s+/', ' ', $content);
    $content = preg_replace('/\{\{ \$[a-zA-Z_]+.*?\}\}/', '{{ VAR }}', $content);
    $content = preg_replace('/@lang\([^)]+\)/', '@lang()', $content);
    $content = preg_replace('/@json\([^)]+\)/', '@json()', $content);
    return trim($content);
}

function getContentFingerprint(string $content): array {
    // Fast pre-filter: line count, normalized length, and a simple hash
    $normalized = normalizeBladeContent($content);
    $lines = substr_count($normalized, "\n") + 1;
    $length = strlen($normalized);
    $hash = crc32($normalized);
    return [$lines, $length, $hash];
}

function similarity(string $a, string $b): float {
    $a = normalizeBladeContent($a);
    $b = normalizeBladeContent($b);
    if ($a === $b) return 1.0;
    if (strlen($a) < 50 || strlen($b) < 50) return 0.0;

    // Fast pre-filter: if lengths differ by more than 30%, skip
    $lenA = strlen($a);
    $lenB = strlen($b);
    $ratio = $lenA > $lenB ? $lenB / $lenA : $lenA / $lenB;
    if ($ratio < 0.70) return 0.0;

    // Use levenshtein distance for fast approximate comparison
    // levenshtein is O(n*m) but much faster than similar_text's O(n^3)
    $maxLen = max($lenA, $lenB);
    if ($maxLen > 5000) {
        // For very long strings, sample first 5000 chars
        $a = substr($a, 0, 5000);
        $b = substr($b, 0, 5000);
        $lenA = $lenB = 5000;
        $maxLen = 5000;
    }
    $dist = levenshtein($a, $b);
    $similarity = 1 - ($dist / $maxLen);
    return max(0.0, min(1.0, $similarity));
}

function getOwnership(string $relativePath): ?string {
    global $SHARED_OWNERSHIP;
    foreach ($SHARED_OWNERSHIP as $path => $owner) {
        if (str_starts_with($relativePath, $path)) {
            return $owner;
        }
    }
    return null;
}

// ─── Task 1: Duplicate Blade Detection ──────────────────────────────────────

function checkDuplicateBlades(): array {
    $violations = [];

    if (!is_dir(CUSTOMER_ROOT)) {
        writeln("  ⚠ customer-site not found at: " . CUSTOMER_ROOT, 'yellow');
        return $violations;
    }

    $saasFiles = findAllBladeFiles(SAAS_ROOT);
    $customerFiles = findAllBladeFiles(CUSTOMER_ROOT);

    writeln("  Scanning " . count($saasFiles) . " saas-platform Blade files...", 'cyan');
    writeln("  Scanning " . count($customerFiles) . " customer-site Blade files...", 'cyan');

    // Build saas-platform file map indexed by filename for O(1) lookup
    $saasMap = [];
    foreach ($saasFiles as $file) {
        $rel = getRelativePath($file, SAAS_ROOT);
        $filename = basename($rel);
        $saasMap[$filename][] = ['rel' => $rel, 'content' => file_get_contents($file)];
    }

    // Check each customer-site file for duplication
    foreach ($customerFiles as $customerFile) {
        $customerRel = getRelativePath($customerFile, CUSTOMER_ROOT);

        // Skip whitelisted files
        if (isWhitelisted($customerRel)) continue;

        $customerContent = file_get_contents($customerFile);
        $customerFilename = basename($customerRel);

        // Only check saas files with the same filename
        if (!isset($saasMap[$customerFilename])) continue;

        foreach ($saasMap[$customerFilename] as $saasEntry) {
            $sim = similarity($customerContent, $saasEntry['content']);

            if ($sim >= SIMILARITY_THRESHOLD) {
                $violations[] = [
                    'type' => 'duplicate_blade',
                    'customer_file' => $customerRel,
                    'saas_file' => $saasEntry['rel'],
                    'similarity' => round($sim * 100, 1),
                    'severity' => $sim >= 0.95 ? 'high' : 'medium',
                ];
            }
        }
    }

    return $violations;
}

// ─── Task 2: Duplicate Controller Detection ─────────────────────────────────

function checkDuplicateControllers(): array {
    $violations = [];

    if (!is_dir(CUSTOMER_ROOT)) {
        return $violations;
    }

    $saasControllers = findAllControllerFiles(SAAS_ROOT);
    $customerControllers = findAllControllerFiles(CUSTOMER_ROOT);

    writeln("  Scanning " . count($saasControllers) . " saas-platform controllers...", 'cyan');
    writeln("  Scanning " . count($customerControllers) . " customer-site controllers...", 'cyan');

    // Build saas-platform controller map indexed by filename for O(1) lookup
    $saasControllerMap = [];
    foreach ($saasControllers as $file) {
        $rel = getRelativePath($file, SAAS_ROOT);
        $filename = basename($rel);
        $saasControllerMap[$filename][] = ['rel' => $rel, 'content' => file_get_contents($file)];
    }

    foreach ($customerControllers as $customerFile) {
        $customerRel = getRelativePath($customerFile, CUSTOMER_ROOT);
        if (isWhitelisted($customerRel)) continue;

        $customerContent = file_get_contents($customerFile);
        $customerFilename = basename($customerRel);

        if (!isset($saasControllerMap[$customerFilename])) continue;

        foreach ($saasControllerMap[$customerFilename] as $saasEntry) {
            $sim = similarity($customerContent, $saasEntry['content']);

            if ($sim >= SIMILARITY_THRESHOLD) {
                $violations[] = [
                    'type' => 'duplicate_controller',
                    'customer_file' => $customerRel,
                    'saas_file' => $saasEntry['rel'],
                    'similarity' => round($sim * 100, 1),
                    'severity' => $sim >= 0.95 ? 'high' : 'medium',
                ];
            }
        }
    }

    return $violations;
}

// ─── Task 3: Shared UI Ownership Enforcement ────────────────────────────────

function checkSharedUiOwnership(): array {
    $violations = [];

    if (!is_dir(CUSTOMER_ROOT)) {
        return $violations;
    }

    // Check customer-site components directory for files that exist in saas-platform
    $customerComponents = CUSTOMER_ROOT . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components';
    $saasComponents = SAAS_ROOT . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'components';

    if (!is_dir($customerComponents) || !is_dir($saasComponents)) {
        return $violations;
    }

    $saasComponentFiles = [];
    $iterator = new FilesystemIterator($saasComponents, FilesystemIterator::SKIP_DOTS);
    foreach ($iterator as $file) {
        $saasComponentFiles[$file->getFilename()] = file_get_contents($file->getPathname());
    }

    $iterator = new FilesystemIterator($customerComponents, FilesystemIterator::SKIP_DOTS);
    foreach ($iterator as $file) {
        $filename = $file->getFilename();
        if (isWhitelisted('resources/views/components/' . $filename)) continue;

        // Check if this component exists in saas-platform
        if (isset($saasComponentFiles[$filename])) {
            $sim = similarity(file_get_contents($file->getPathname()), $saasComponentFiles[$filename]);
            if ($sim >= SIMILARITY_THRESHOLD) {
                $violations[] = [
                    'type' => 'shared_ui_duplication',
                    'customer_file' => 'resources/views/components/' . $filename,
                    'saas_file' => 'resources/views/components/' . $filename,
                    'similarity' => round($sim * 100, 1),
                    'severity' => 'high',
                    'message' => "کامپوننت {$filename} در customer-site تکرار شده است. باید از saas-platform/ui-kit بارگذاری شود.",
                ];
            }
        }
    }

    return $violations;
}

// ─── Task 4: Package Ownership Validation ───────────────────────────────────

function checkPackageOwnership(): array {
    $violations = [];

    // Check for unauthorized edits in shared package directories
    $sharedDirs = [
        SAAS_ROOT . DIRECTORY_SEPARATOR . 'packages',
        SAAS_ROOT . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'saas-platform',
    ];

    foreach ($sharedDirs as $dir) {
        if (!is_dir($dir)) continue;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;

            $content = file_get_contents($file->getPathname());
            $relPath = getRelativePath($file->getPathname(), SAAS_ROOT);

            // Check for namespace violations
            if (preg_match('/^namespace\s+(.+);/m', $content, $m)) {
                $namespace = $m[1];
                // Shared packages should use SaasPlatform\Shared namespace
                if (!str_starts_with($namespace, 'SaaSPlatform\\')) {
                    $violations[] = [
                        'type' => 'namespace_violation',
                        'file' => $relPath,
                        'namespace' => $namespace,
                        'severity' => 'medium',
                        'message' => "فضای نام {$namespace} در بسته اشتراکی مجاز نیست.",
                    ];
                }
            }
        }
    }

    return $violations;
}

// ─── Task 7: Shared Contract Validation ─────────────────────────────────────

function checkContracts(): array {
    $violations = [];

    // Check module.json files exist and are valid
    $moduleDirs = [
        SAAS_ROOT . DIRECTORY_SEPARATOR . 'packages',
    ];

    foreach ($moduleDirs as $dir) {
        if (!is_dir($dir)) continue;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getFilename() !== 'module.json') continue;

            $content = file_get_contents($file->getPathname());
            $manifest = json_decode($content, true);

            if (!$manifest) {
                $violations[] = [
                    'type' => 'invalid_manifest',
                    'file' => getRelativePath($file->getPathname(), SAAS_ROOT),
                    'severity' => 'high',
                    'message' => 'module.json معتبر نیست.',
                ];
                continue;
            }

            // Validate required fields
            $required = ['module_slug', 'version', 'update_type', 'compatibility'];
            foreach ($required as $field) {
                if (!isset($manifest[$field])) {
                    $violations[] = [
                        'type' => 'missing_manifest_field',
                        'file' => getRelativePath($file->getPathname(), SAAS_ROOT),
                        'field' => $field,
                        'severity' => 'high',
                        'message' => "فیلد {$field} در module.json وجود ندارد.",
                    ];
                }
            }
        }
    }

    return $violations;
}

// ─── Task 8: Drift Report Generation ────────────────────────────────────────

function generateReport(array $allViolations): string {
    $report = [];
    $report[] = "═══════════════════════════════════════════════════════";
    $report[] = "  PHASE 5 — ANTI-DRIFT REPORT";
    $report[] = "  Generated: " . date('Y-m-d H:i:s');
    $report[] = "═══════════════════════════════════════════════════════";
    $report[] = "";

    if (empty($allViolations)) {
        $report[] = "  ✅ No drift violations detected.";
        $report[] = "";
        $report[] = "  All systems clean. Shared modules are properly synchronized.";
        $report[] = "";
        return implode(PHP_EOL, $report);
    }

    // Group by type
    $grouped = [];
    foreach ($allViolations as $v) {
        $grouped[$v['type']][] = $v;
    }

    $totalHigh = count(array_filter($allViolations, fn($v) => $v['severity'] === 'high'));
    $totalMedium = count(array_filter($allViolations, fn($v) => $v['severity'] === 'medium'));

    $report[] = "  📊 Summary:";
    $report[] = "     Total violations: " . count($allViolations);
    $report[] = "     🔴 High severity:  {$totalHigh}";
    $report[] = "     🟡 Medium severity: {$totalMedium}";
    $report[] = "";

    foreach ($grouped as $type => $violations) {
        $labels = [
            'duplicate_blade' => '📄 Duplicate Blade Files',
            'duplicate_controller' => '⚙ Duplicate Controllers',
            'shared_ui_duplication' => '🎨 Shared UI Duplication',
            'namespace_violation' => '🏷 Namespace Violations',
            'invalid_manifest' => '📦 Invalid Manifests',
            'missing_manifest_field' => '📦 Missing Manifest Fields',
        ];

        $label = $labels[$type] ?? $type;
        $report[] = "  ─── {$label} ───";
        $report[] = "";

        foreach ($violations as $v) {
            $severityIcon = $v['severity'] === 'high' ? '🔴' : '🟡';
            $report[] = "  {$severityIcon} [{$v['severity']}]";

            if (isset($v['customer_file'])) {
                $report[] = "     customer-site: {$v['customer_file']}";
            }
            if (isset($v['saas_file'])) {
                $report[] = "     saas-platform: {$v['saas_file']}";
            }
            if (isset($v['file'])) {
                $report[] = "     file: {$v['file']}";
            }
            if (isset($v['similarity'])) {
                $report[] = "     similarity: {$v['similarity']}%";
            }
            if (isset($v['message'])) {
                $report[] = "     message: {$v['message']}";
            }
            if (isset($v['namespace'])) {
                $report[] = "     namespace: {$v['namespace']}";
            }
            if (isset($v['field'])) {
                $report[] = "     missing field: {$v['field']}";
            }
            $report[] = "";
        }
    }

    $report[] = "  ─── Recommendations ───";
    $report[] = "";
    if ($totalHigh > 0) {
        $report[] = "  🔴 High severity violations MUST be resolved before deployment.";
    }
    if (!empty($grouped['duplicate_blade'] ?? [])) {
        $report[] = "  📄 Move duplicate Blade files to shared packages.";
    }
    if (!empty($grouped['shared_ui_duplication'] ?? [])) {
        $report[] = "  🎨 Load shared UI components from ui-kit instead of duplicating.";
    }
    if (!empty($grouped['namespace_violation'] ?? [])) {
        $report[] = "  🏷 Fix namespaces to use SaasPlatform\\Shared\\ prefix.";
    }
    $report[] = "";
    $report[] = "═══════════════════════════════════════════════════════";

    return implode(PHP_EOL, $report);
}

// ─── Task 9: Exception/Waiver System ────────────────────────────────────────

function addException(string $filePath, string $reason, string $expiresAt = ''): bool {
    $whitelist = loadWhitelist();

    $entry = [
        'file' => $filePath,
        'reason' => $reason,
        'added_by' => get_current_user(),
        'added_at' => date('Y-m-d H:i:s'),
    ];

    if ($expiresAt) {
        $entry['expires_at'] = $expiresAt;
    }

    $whitelist[] = $filePath;

    $data = ['files' => $whitelist];
    file_put_contents(WHITELIST_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    writeln("  ✅ Exception added for: {$filePath}", 'green');
    return true;
}

function listExceptions(): void {
    $whitelist = loadWhitelist();
    if (empty($whitelist)) {
        writeln("  No exceptions configured.", 'yellow');
        return;
    }
    writeln("  Active exceptions (" . count($whitelist) . "):", 'cyan');
    foreach ($whitelist as $entry) {
        writeln("    - {$entry}");
    }
}

// ─── Main Execution ─────────────────────────────────────────────────────────

function main(array $argv): int {
    $isCI = in_array('--ci', $argv);
    $generateReportFlag = in_array('--report', $argv);
    $checkBlade = in_array('--check-blade', $argv);
    $checkControllers = in_array('--check-controllers', $argv);
    $checkOwnership = in_array('--check-ownership', $argv);
    $checkContracts = in_array('--check-contracts', $argv);
    $addExceptionFlag = in_array('--add-exception', $argv);
    $listExceptionsFlag = in_array('--list-exceptions', $argv);

    // Handle exception management
    if ($addExceptionFlag) {
        $fileIdx = array_search('--add-exception', $argv) + 1;
        $reasonIdx = $fileIdx + 1;
        $expiresIdx = $reasonIdx + 1;

        $filePath = $argv[$fileIdx] ?? '';
        $reason = $argv[$reasonIdx] ?? 'Manual exception';
        $expires = $argv[$expiresIdx] ?? '';

        if (!$filePath) {
            writeln("  Usage: --add-exception <file> <reason> [expires_at]", 'red');
            return 1;
        }

        addException($filePath, $reason, $expires);
        return 0;
    }

    if ($listExceptionsFlag) {
        listExceptions();
        return 0;
    }

    writeln("");
    writeln("╔══════════════════════════════════════════════════════╗", 'bold');
    writeln("║   PHASE 5 — ANTI-DRIFT DETECTION ENGINE             ║", 'bold');
    writeln("║   Lightweight Executable Enforcement Layer           ║", 'bold');
    writeln("╚══════════════════════════════════════════════════════╝", 'bold');
    writeln("");

    $allViolations = [];

    // Task 1: Duplicate Blade Detection
    if (!$checkControllers && !$checkOwnership && !$checkContracts || $checkBlade) {
        writeln("─── Task 1: Duplicate Blade Detection ───", 'bold');
        $violations = checkDuplicateBlades();
        $allViolations = array_merge($allViolations, $violations);
        writeln("  " . count($violations) . " violation(s) found.", count($violations) > 0 ? 'red' : 'green');
        writeln("");
    }

    // Task 2: Duplicate Controller Detection
    if (!$checkBlade && !$checkOwnership && !$checkContracts || $checkControllers) {
        writeln("─── Task 2: Duplicate Controller Detection ───", 'bold');
        $violations = checkDuplicateControllers();
        $allViolations = array_merge($allViolations, $violations);
        writeln("  " . count($violations) . " violation(s) found.", count($violations) > 0 ? 'red' : 'green');
        writeln("");
    }

    // Task 3: Shared UI Ownership Enforcement
    if (!$checkBlade && !$checkControllers && !$checkContracts || $checkOwnership) {
        writeln("─── Task 3: Shared UI Ownership Enforcement ───", 'bold');
        $violations = checkSharedUiOwnership();
        $allViolations = array_merge($allViolations, $violations);
        writeln("  " . count($violations) . " violation(s) found.", count($violations) > 0 ? 'red' : 'green');
        writeln("");
    }

    // Task 4: Package Ownership Validation
    if (!$checkBlade && !$checkControllers && !$checkContracts || $checkOwnership) {
        writeln("─── Task 4: Package Ownership Validation ───", 'bold');
        $violations = checkPackageOwnership();
        $allViolations = array_merge($allViolations, $violations);
        writeln("  " . count($violations) . " violation(s) found.", count($violations) > 0 ? 'red' : 'green');
        writeln("");
    }

    // Task 7: Shared Contract Validation
    if (!$checkBlade && !$checkControllers && !$checkOwnership || $checkContracts) {
        writeln("─── Task 7: Shared Contract Validation ───", 'bold');
        $violations = checkContracts();
        $allViolations = array_merge($allViolations, $violations);
        writeln("  " . count($violations) . " violation(s) found.", count($violations) > 0 ? 'red' : 'green');
        writeln("");
    }

    // Generate report
    if ($generateReportFlag || $isCI) {
        $report = generateReport($allViolations);

        $reportFile = SAAS_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'drift-report-' . date('Y-m-d') . '.txt';
        file_put_contents($reportFile, $report);

        writeln("─── Report ───", 'bold');
        writeln($report);
        writeln("");
        writeln("  Report saved to: {$reportFile}", 'cyan');
    }

    // CI mode: exit with error code if violations found
    if ($isCI) {
        $highSeverity = array_filter($allViolations, fn($v) => $v['severity'] === 'high');
        if (!empty($highSeverity)) {
            writeln("  ❌ CI FAILED: " . count($highSeverity) . " high-severity drift violation(s) detected.", 'red');
            return 1;
        }
        if (!empty($allViolations)) {
            writeln("  ⚠ CI WARNING: " . count($allViolations) . " medium-severity drift violation(s) detected.", 'yellow');
        }
        writeln("  ✅ CI PASSED: No high-severity drift violations.", 'green');
    }

    return 0;
}

exit(main($argv));
