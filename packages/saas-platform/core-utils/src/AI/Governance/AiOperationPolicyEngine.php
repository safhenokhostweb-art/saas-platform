<?php

namespace SaaSPlatform\CoreUtils\AI\Governance;

class AiOperationPolicyEngine
{
    private array $policies = [];

    public function __construct()
    {
        $this->loadDefaultPolicies();
    }

    public function registerPolicy(string $name, array $policy): void
    {
        $this->policies[$name] = $policy;
    }

    /**
     * Evaluate all policies for a given AI operation.
     * Returns: ['allowed', 'reason', 'violations']
     */
    public function evaluate(string $domain, string $operation, array $context = []): array
    {
        $violations = [];

        foreach ($this->policies as $name => $policy) {
            if (! $this->policyApplies($policy, $domain, $operation)) continue;

            $result = $this->checkPolicy($policy, $context);
            if (! $result['allowed']) {
                $violations[] = ['policy' => $name, 'reason' => $result['reason']];
            }
        }

        return [
            'allowed'    => empty($violations),
            'violations' => $violations,
            'reason'     => empty($violations) ? 'all_policies_passed' : $violations[0]['reason'],
        ];
    }

    public function isOperationAllowed(string $domain, string $operation, array $context = []): bool
    {
        return $this->evaluate($domain, $operation, $context)['allowed'];
    }

    public function getPolicies(): array
    {
        return array_keys($this->policies);
    }

    private function policyApplies(array $policy, string $domain, string $operation): bool
    {
        $domains    = $policy['domains'] ?? [];
        $operations = $policy['operations'] ?? [];

        $domainMatch = empty($domains) || in_array($domain, $domains) || in_array('*', $domains);
        $opMatch     = empty($operations) || in_array($operation, $operations) || in_array('*', $operations);

        return $domainMatch && $opMatch;
    }

    private function checkPolicy(array $policy, array $context): array
    {
        $type = $policy['type'] ?? 'allow';

        return match($type) {
            'kill_switch' => $this->checkKillSwitch(),
            'feature_flag'=> $this->checkFeatureFlag($policy, $context),
            'sandbox'     => $this->checkSandbox(),
            'rollout'     => $this->checkRollout($context),
            default       => ['allowed' => true, 'reason' => 'no_check'],
        };
    }

    private function checkKillSwitch(): array
    {
        $active = config('ai.emergency.kill_switch', false);
        return ['allowed' => ! $active, 'reason' => $active ? 'kill_switch_active' : 'ok'];
    }

    private function checkFeatureFlag(array $policy, array $context): array
    {
        $flag = $policy['flag'] ?? '';
        if (! $flag) return ['allowed' => true, 'reason' => 'no_flag'];

        $enabled = (bool) config("ai.features.{$flag}", true);
        return ['allowed' => $enabled, 'reason' => $enabled ? 'ok' : "feature_{$flag}_disabled"];
    }

    private function checkSandbox(): array
    {
        $sandbox = config('ai.security.sandbox_mode', false);
        return ['allowed' => ! $sandbox, 'reason' => $sandbox ? 'sandbox_mode_active' : 'ok'];
    }

    private function checkRollout(array $context): array
    {
        $pct      = config('ai.rollout_percentage', 100);
        $tenantId = $context['tenant_id'] ?? '';

        if ($pct >= 100) return ['allowed' => true, 'reason' => 'full_rollout'];
        if (empty($tenantId)) return ['allowed' => true, 'reason' => 'no_tenant'];

        $hash    = crc32($tenantId) % 100;
        $allowed = abs($hash) < $pct;

        return ['allowed' => $allowed, 'reason' => $allowed ? 'in_rollout' : 'not_in_rollout'];
    }

    private function loadDefaultPolicies(): void
    {
        $this->registerPolicy('kill_switch', [
            'type'    => 'kill_switch',
            'domains' => ['*'],
            'operations' => ['*'],
        ]);

        $this->registerPolicy('forms_feature_flag', [
            'type'       => 'feature_flag',
            'flag'       => 'forms_ai',
            'domains'    => ['forms'],
            'operations' => ['*'],
        ]);

        $this->registerPolicy('tickets_feature_flag', [
            'type'       => 'feature_flag',
            'flag'       => 'tickets_ai',
            'domains'    => ['tickets'],
            'operations' => ['*'],
        ]);

        $this->registerPolicy('staged_rollout', [
            'type'       => 'rollout',
            'domains'    => ['*'],
            'operations' => ['*'],
        ]);
    }
}
