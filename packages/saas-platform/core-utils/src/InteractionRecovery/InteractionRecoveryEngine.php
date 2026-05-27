<?php

namespace SaaSPlatform\CoreUtils\InteractionRecovery;

use Illuminate\Support\Facades\Cache;

class InteractionRecoveryEngine
{
    private const RECOVERY_LOG_KEY = 'interaction:recovery:log:';
    private array $recoveryStrategies = [];

    public function registerStrategy(string $failureType, callable $strategy): void
    {
        $this->recoveryStrategies[$failureType] = $strategy;
    }

    public function reportFailure(string $tenantId, string $failureType, array $context = []): array
    {
        $entry = [
            'id'           => uniqid('rf_', true),
            'type'         => $failureType,
            'context'      => $context,
            'reported_at'  => now()->toIso8601String(),
            'recovered'    => false,
            'recovery_action' => null,
        ];

        $recovery = $this->attemptRecovery($tenantId, $failureType, $context);
        $entry['recovered']       = $recovery['success'] ?? false;
        $entry['recovery_action'] = $recovery['action'] ?? null;

        $this->logFailure($tenantId, $entry);

        return $entry;
    }

    public function attemptRecovery(string $tenantId, string $failureType, array $context): array
    {
        if (isset($this->recoveryStrategies[$failureType])) {
            try {
                $result = ($this->recoveryStrategies[$failureType])($tenantId, $context);
                return ['success' => true, 'action' => $result];
            } catch (\Throwable $e) {
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        return $this->getDefaultRecovery($failureType, $context);
    }

    public function getRecoveryLog(string $tenantId, int $limit = 20): array
    {
        $key = self::RECOVERY_LOG_KEY . $tenantId;
        $log = Cache::get($key, []);
        return array_slice(array_reverse($log), 0, $limit);
    }

    public function getStats(string $tenantId): array
    {
        $log = Cache::get(self::RECOVERY_LOG_KEY . $tenantId, []);
        return [
            'total'     => count($log),
            'recovered' => count(array_filter($log, fn ($e) => $e['recovered'])),
            'by_type'   => array_count_values(array_column($log, 'type')),
        ];
    }

    public function getClientRecoveryScript(): string
    {
        return <<<'JS'
(function() {
    // Self-healing Alpine state recovery
    window._recoveryEngine = {
        deadlocks: 0,
        staleStates: 0,

        // Recover broken Alpine components
        healAlpineStates() {
            if (!window.Alpine) return;
            document.querySelectorAll('[x-data]').forEach(el => {
                try {
                    const c = window.Alpine.$data(el);
                    if (c && typeof c._recover === 'function') c._recover();
                } catch(e) { this.reportFailure('alpine_state', { selector: el.id ?? 'unknown' }); }
            });
        },

        // Recover modal deadlocks (body scroll lock stuck)
        healModalDeadlocks() {
            const hasOpenModal = document.querySelector('[role="dialog"][aria-modal="true"]');
            if (!hasOpenModal && document.body.style.overflow === 'hidden') {
                document.body.style.overflow = '';
                this.deadlocks++;
                this.reportFailure('modal_deadlock', {});
            }
        },

        // Clear stale optimistic UI
        healOptimisticUI() {
            document.querySelectorAll('[data-optimistic-pending]').forEach(el => {
                const since = parseInt(el.dataset.optimisticSince ?? 0);
                if (Date.now() - since > 15000) {
                    el.removeAttribute('data-optimistic-pending');
                    el.removeAttribute('data-optimistic-since');
                    this.staleStates++;
                    this.reportFailure('stale_optimistic_ui', { element: el.tagName });
                }
            });
        },

        // Remove duplicated event listeners (detect by data attribute)
        healDuplicateListeners() {
            document.querySelectorAll('[data-listener-id]').forEach(el => {
                const id = el.dataset.listenerId;
                const matches = document.querySelectorAll(`[data-listener-id="${id}"]`);
                if (matches.length > 1) {
                    for (let i = 1; i < matches.length; i++) matches[i].removeAttribute('data-listener-id');
                }
            });
        },

        reportFailure(type, ctx) {
            fetch('/api/interaction-recovery/report', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                body: JSON.stringify({ type, context: ctx })
            }).catch(() => {});
        },

        start(intervalMs = 30000) {
            setInterval(() => {
                this.healModalDeadlocks();
                this.healOptimisticUI();
                this.healDuplicateListeners();
            }, intervalMs);
            // Heal Alpine states on DOM ready
            if (document.readyState === 'complete') this.healAlpineStates();
            else window.addEventListener('load', () => this.healAlpineStates());
        }
    };

    document.addEventListener('DOMContentLoaded', () => window._recoveryEngine.start());
})();
JS;
    }

    private function getDefaultRecovery(string $failureType, array $context): array
    {
        $actions = [
            'alpine_state'       => 'reload_component',
            'modal_deadlock'     => 'reset_scroll_lock',
            'stale_optimistic'   => 'rollback_optimistic_state',
            'duplicate_listeners'=> 'remove_duplicate_listeners',
            'stale_session'      => 'refresh_auth_token',
            'race_condition'     => 'queue_and_retry',
        ];

        return [
            'success' => true,
            'action'  => $actions[$failureType] ?? 'log_and_continue',
        ];
    }

    private function logFailure(string $tenantId, array $entry): void
    {
        $key   = self::RECOVERY_LOG_KEY . $tenantId;
        $log   = Cache::get($key, []);
        $log[] = $entry;
        if (count($log) > 200) {
            $log = array_slice($log, -200);
        }
        Cache::put($key, $log, 86400 * 7);
    }
}
