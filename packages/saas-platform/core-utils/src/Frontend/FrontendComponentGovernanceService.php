<?php

namespace SaaSPlatform\CoreUtils\Frontend;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;

/**
 * Phase 10.1 — Task 3
 * Detects raw HTML elements and non-canonical component usage across all module views.
 * Enforces the Enterprise Frontend Authority Law: all UI primitives must originate
 * from x-core-ui::* canonical components.
 */
class FrontendComponentGovernanceService
{
    private array $scanPaths = [];
    private array $findings  = [];
    private array $stats     = [];

    private const FORBIDDEN_RAW_ELEMENTS = [
        'raw_button'   => ['pattern' => '/<button\b(?![^>]*x-core-ui)/i',  'message' => 'Raw <button> outside x-core-ui::button'],
        'raw_input'    => ['pattern' => '/<input\b(?![^>]*x-core-ui)(?![^>]*(type=["\']hidden|type=["\']file|type=["\']checkbox|type=["\']radio))/i', 'message' => 'Raw <input> outside x-core-ui form controls'],
        'raw_select'   => ['pattern' => '/<select\b(?![^>]*x-core-ui)/i',  'message' => 'Raw <select> outside x-core-ui::select'],
        'raw_textarea' => ['pattern' => '/<textarea\b(?![^>]*x-core-ui)/i','message' => 'Raw <textarea> outside x-core-ui::textarea'],
    ];

    private const FORBIDDEN_PATTERNS = [
        'inline_style'        => ['pattern' => '/style\s*=\s*["\'][^"\']*(?:color|background|font-size|margin|padding|width|height|z-index|border)[^"\']*["\']/i', 'message' => 'Inline style attribute detected — use design tokens'],
        'hardcoded_hex'       => ['pattern' => '/#[0-9a-fA-F]{3,8}\b(?![\w-])/', 'message' => 'Hardcoded hex color — replace with --ui-* token'],
        'hardcoded_tailwind_gray' => ['pattern' => '/\b(?:text|bg|border)-gray-\d+\b/', 'message' => 'Hardcoded gray Tailwind class — replace with token var'],
        'hardcoded_tailwind_green'=> ['pattern' => '/\b(?:text|bg|border)-green-\d+\b/', 'message' => 'Hardcoded green Tailwind class — replace with --ui-success-* token'],
        'hardcoded_tailwind_red'  => ['pattern' => '/\b(?:text|bg|border)-red-\d+\b/', 'message' => 'Hardcoded red Tailwind class — replace with --ui-danger-* token'],
        'hardcoded_zindex'    => ['pattern' => '/z-index\s*:\s*\d+/', 'message' => 'Hardcoded z-index — use --ui-z-* token'],
        'bg_white'            => ['pattern' => '/\bbg-white\b/', 'message' => 'bg-white — replace with bg-[var(--ui-surface)]'],
        'text_white_raw'      => ['pattern' => '/\btext-white\b(?![^}]*fill)/', 'message' => 'text-white may need token replacement'],
        'duration_raw'        => ['pattern' => '/\bduration-(?!150|200|300)\d+\b/', 'message' => 'Non-canonical transition duration — use --ui-duration-* token'],
    ];

    private const SEVERITY_MAP = [
        'raw_button'           => 'HIGH',
        'raw_input'            => 'HIGH',
        'raw_select'           => 'HIGH',
        'raw_textarea'         => 'HIGH',
        'inline_style'         => 'HIGH',
        'hardcoded_hex'        => 'CRITICAL',
        'hardcoded_tailwind_gray'  => 'MEDIUM',
        'hardcoded_tailwind_green' => 'HIGH',
        'hardcoded_tailwind_red'   => 'HIGH',
        'hardcoded_zindex'     => 'CRITICAL',
        'bg_white'             => 'MEDIUM',
        'text_white_raw'       => 'LOW',
        'duration_raw'         => 'MEDIUM',
    ];

    public function __construct()
    {
        $this->scanPaths = $this->resolveScanPaths();
    }

    public function validate(bool $strict = false): array
    {
        $this->findings = [];
        $this->stats    = ['files_scanned' => 0, 'total_violations' => 0, 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];

        foreach ($this->scanPaths as $label => $path) {
            if (! is_dir($path)) continue;
            $this->scanDirectory($path, $label);
        }

        $this->stats['total_violations'] = count($this->findings);
        $score = $this->computeScore();

        return [
            'passed'     => $this->stats['critical'] === 0 && (! $strict || $this->stats['high'] === 0),
            'score'      => $score,
            'stats'      => $this->stats,
            'findings'   => $this->findings,
            'summary'    => $this->buildSummary($score),
        ];
    }

    private function scanDirectory(string $path, string $label): void
    {
        $files = File::allFiles($path);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;

            $this->stats['files_scanned']++;
            $content = file_get_contents($file->getPathname());
            $lines   = explode("\n", $content);

            foreach (self::FORBIDDEN_RAW_ELEMENTS as $key => $rule) {
                $this->scanWithRegex($file->getPathname(), $lines, $key, $rule['pattern'], $rule['message'], $label);
            }

            foreach (self::FORBIDDEN_PATTERNS as $key => $rule) {
                $this->scanWithRegex($file->getPathname(), $lines, $key, $rule['pattern'], $rule['message'], $label);
            }

            // Detect duplicate modal logic (custom Alpine modals not using core-ui::modal)
            if (preg_match('/x-show.*modal|@click.*open.*modal|alpine.*modal/i', $content)
                && ! preg_match('/x-core-ui::modal|x-core-ui::confirm-modal/i', $content)) {
                $this->addFinding($file->getPathname(), 0, 'duplicate_modal', 'Custom Alpine modal detected — migrate to x-core-ui::modal', 'HIGH', $label);
            }

            // Detect notification-center duplication
            if (preg_match('/notification.center/i', $content)
                && strpos($file->getPathname(), 'core-utils') === false
                && strpos($file->getPathname(), 'core-ui') === false) {
                $this->addFinding($file->getPathname(), 0, 'duplicate_notification', 'notification-center forked — use x-core-ui::notification-center', 'HIGH', $label);
            }
        }
    }

    private function scanWithRegex(string $filePath, array $lines, string $key, string $pattern, string $message, string $label): void
    {
        foreach ($lines as $lineNo => $line) {
            // Skip comments and Blade comment blocks
            if (preg_match('/^\s*{{--/', $line) || preg_match('/^\s*\/\//', $line) || preg_match('/^\s*\*/', $line)) continue;

            if (preg_match($pattern, $line)) {
                $severity = self::SEVERITY_MAP[$key] ?? 'MEDIUM';
                $this->addFinding($filePath, $lineNo + 1, $key, $message, $severity, $label);
            }
        }
    }

    private function addFinding(string $file, int $line, string $type, string $message, string $severity, string $module): void
    {
        $this->findings[] = compact('file', 'line', 'type', 'message', 'severity', 'module');

        $lower = strtolower($severity);
        if (isset($this->stats[$lower])) {
            $this->stats[$lower]++;
        }
    }

    private function computeScore(): int
    {
        $critical = $this->stats['critical'] ?? 0;
        $high     = $this->stats['high']     ?? 0;
        $medium   = $this->stats['medium']   ?? 0;
        $low      = $this->stats['low']      ?? 0;

        $deduction = ($critical * 5) + ($high * 2) + ($medium * 0.5) + ($low * 0.1);
        return max(0, (int) round(100 - $deduction));
    }

    private function buildSummary(int $score): string
    {
        $grade = match(true) {
            $score >= 95 => 'A+',
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default      => 'F',
        };

        return sprintf(
            'Component Governance Score: %d/100 (Grade %s) | CRITICAL: %d | HIGH: %d | MEDIUM: %d | LOW: %d',
            $score, $grade,
            $this->stats['critical'],
            $this->stats['high'],
            $this->stats['medium'],
            $this->stats['low']
        );
    }

    private function resolveScanPaths(): array
    {
        $base = base_path('packages/saas-platform');
        return [
            'forms-admin'   => $base . '/modules/forms/resources/views/admin',
            'forms-site'    => $base . '/modules/forms/resources/views/site',
            'tickets-admin' => $base . '/modules/tickets/Resources/views/admin',
            'tickets-site'  => $base . '/modules/tickets/Resources/views/site',
            'layouts'       => resource_path('views/layouts'),
            'customer-site' => base_path('../customer-site/resources/views'),
        ];
    }

    public function getScanPaths(): array
    {
        return $this->scanPaths;
    }
}
