<?php

namespace SaaSPlatform\CoreUtils\Frontend;

/**
 * Phase 10.1 — Task 8
 * Unifies frontend runtime states across all modules:
 * loading, skeleton, async save, autosave, error alerts, success toasts,
 * confirmation flows, destructive actions, optimistic UI, retry, offline handling.
 *
 * Provides the canonical state contract that all modules must follow.
 */
class FrontendStateGovernanceService
{
    private const CANONICAL_STATE_PATTERNS = [
        'loading_indicator' => [
            'canonical' => 'x-core-ui::skeleton or x-core-ui::spinner via --ui-spinner class',
            'forbidden' => ['custom-loading', 'loader', 'spinner-custom', 'loading-overlay'],
        ],
        'success_toast' => [
            'canonical' => '$dispatch(\'ui-toast\', { message: \'...\', variant: \'success\' })',
            'forbidden' => ['alert(', 'Swal.', 'toastr.', 'noty('],
        ],
        'error_alert' => [
            'canonical' => 'x-core-ui::alert variant="danger" or $dispatch(\'ui-toast\', { variant: \'danger\' })',
            'forbidden' => ['alert(', 'console.error', 'window.confirm('],
        ],
        'confirm_flow' => [
            'canonical' => 'x-core-ui::confirm-modal',
            'forbidden' => ['confirm(', 'window.confirm(', 'if (confirm('],
        ],
        'async_save' => [
            'canonical' => 'Alpine x-data with loading boolean + x-core-ui::button :loading="loading"',
            'forbidden' => ['disabled="disabled"', 'pointer-events-none manually', 'opacity-50 manually'],
        ],
        'autosave' => [
            'canonical' => 'x-core-ui::autosave-indicator with --forms-anim-autosave token',
            'forbidden' => ['autosave-dot custom', 'saving... text hardcoded'],
        ],
        'empty_state' => [
            'canonical' => 'x-core-ui::empty-state',
            'forbidden' => ['custom empty div', 'p tag alone', 'text-gray-400 by itself'],
        ],
        'optimistic_ui' => [
            'canonical' => 'Alpine immediate state update + rollback on error via $dispatch(\'ui-toast\')',
            'forbidden' => ['page reload on success', 'window.location.reload('],
        ],
    ];

    private const STATE_AUDIT_PATHS = [
        'forms-admin'   => 'modules/forms/resources/views/admin',
        'tickets-admin' => 'modules/tickets/Resources/views/admin',
        'core-utils'    => 'packages/saas-platform/core-utils/resources/views',
    ];

    public function auditStateConsistency(): array
    {
        $results = [];
        $violations = [];

        foreach (self::STATE_AUDIT_PATHS as $module => $relPath) {
            $path = base_path('packages/saas-platform/' . $relPath);
            if (! is_dir($path)) {
                $path = base_path($relPath);
            }
            if (! is_dir($path)) continue;

            $files = $this->getBladeFiles($path);
            foreach ($files as $file) {
                $content = file_get_contents($file);
                foreach (self::CANONICAL_STATE_PATTERNS as $state => $rule) {
                    foreach ($rule['forbidden'] as $forbidden) {
                        if (str_contains($content, $forbidden)) {
                            $violations[] = [
                                'module'     => $module,
                                'file'       => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file),
                                'state'      => $state,
                                'found'      => $forbidden,
                                'canonical'  => $rule['canonical'],
                                'severity'   => $this->classifySeverity($state),
                            ];
                        }
                    }
                }
            }
        }

        $score = max(0, 100 - (count($violations) * 4));

        return [
            'score'      => $score,
            'violations' => $violations,
            'patterns'   => array_keys(self::CANONICAL_STATE_PATTERNS),
            'summary'    => $this->buildSummary($score, $violations),
        ];
    }

    public function getStateContract(): array
    {
        return self::CANONICAL_STATE_PATTERNS;
    }

    public function getAlpineStateTemplate(string $state): string
    {
        return match($state) {
            'loading' => "{ loading: false, async submit() { this.loading = true; try { await fetch(...); \$dispatch('ui-toast', { message: 'ذخیره شد', variant: 'success' }); } catch(e) { \$dispatch('ui-toast', { message: 'خطا رخ داد', variant: 'danger' }); } finally { this.loading = false; } } }",
            'confirm' => "{ open: false, confirmed() { this.open = false; /* perform action */ } }",
            'optimistic' => "{ items: [], add(item) { this.items.push(item); try { await api.save(item); } catch(e) { this.items.pop(); \$dispatch('ui-toast', { message: 'ذخیره ناموفق', variant: 'danger' }); } }",
            default    => "{ /* state: {$state} — follow canonical pattern */ }",
        };
    }

    private function classifySeverity(string $state): string
    {
        return match($state) {
            'confirm_flow'    => 'CRITICAL',
            'error_alert'     => 'HIGH',
            'loading_indicator','success_toast' => 'HIGH',
            default           => 'MEDIUM',
        };
    }

    private function getBladeFiles(string $path): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        return $files;
    }

    private function buildSummary(int $score, array $violations): string
    {
        $critical = count(array_filter($violations, fn ($v) => $v['severity'] === 'CRITICAL'));
        $high     = count(array_filter($violations, fn ($v) => $v['severity'] === 'HIGH'));

        return sprintf(
            'State Governance Score: %d/100 | Violations: %d (CRITICAL: %d, HIGH: %d)',
            $score, count($violations), $critical, $high
        );
    }
}
