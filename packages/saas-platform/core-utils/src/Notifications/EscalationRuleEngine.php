<?php

namespace SaaSPlatform\CoreUtils\Notifications;

use Illuminate\Support\Facades\Cache;

class EscalationRuleEngine
{
    private array $defaultRules = [
        ['event' => 'ticket.sla_warn',   'delay_minutes' => 0,  'severity' => 'warning',  'channels' => ['in_app', 'email']],
        ['event' => 'ticket.sla_breach', 'delay_minutes' => 0,  'severity' => 'critical', 'channels' => ['in_app', 'email', 'sms']],
        ['event' => 'ticket.escalated',  'delay_minutes' => 0,  'severity' => 'warning',  'channels' => ['in_app', 'email']],
        ['event' => 'ticket.unresponded','delay_minutes' => 60, 'severity' => 'warning',  'channels' => ['in_app']],
    ];

    public function getRulesFor(string $tenantId, string $event): array
    {
        $key    = "escalation:rules:{$tenantId}";
        $custom = Cache::get($key, []);
        $all    = array_merge($this->defaultRules, $custom);
        return array_values(array_filter($all, fn($r) => $r['event'] === $event));
    }

    public function saveRule(string $tenantId, array $rule): void
    {
        $key   = "escalation:rules:{$tenantId}";
        $rules = Cache::get($key, []);
        // Replace existing rule for same event if exists
        $rules = array_filter($rules, fn($r) => $r['event'] !== $rule['event']);
        $rules[] = $rule;
        Cache::put($key, array_values($rules), now()->addDays(365));
    }

    public function evaluate(string $tenantId, string $event, array $context): array
    {
        $rules = $this->getRulesFor($tenantId, $event);
        $actions = [];
        foreach ($rules as $rule) {
            if ($this->conditionsMet($rule, $context)) {
                $actions[] = [
                    'rule'     => $rule,
                    'channels' => $rule['channels'] ?? ['in_app'],
                    'severity' => $rule['severity'] ?? 'info',
                ];
            }
        }
        return $actions;
    }

    private function conditionsMet(array $rule, array $context): bool
    {
        if (isset($rule['conditions'])) {
            foreach ($rule['conditions'] as $field => $value) {
                if (($context[$field] ?? null) !== $value) {
                    return false;
                }
            }
        }
        return true;
    }
}
