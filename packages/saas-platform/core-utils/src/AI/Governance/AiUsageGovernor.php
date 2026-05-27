<?php

namespace SaaSPlatform\CoreUtils\AI\Governance;

class AiUsageGovernor
{
    public function __construct(
        private readonly TenantAiQuotaManager    $quotaManager,
        private readonly AiRateLimitCoordinator  $rateLimiter,
        private readonly AiRequestBudgetManager  $budgetManager,
        private readonly AiOperationPolicyEngine $policyEngine,
        private readonly AiCostEstimator         $costEstimator,
    ) {}

    /**
     * Comprehensive governance check before executing an AI operation.
     */
    public function authorize(
        string $tenantId,
        string $domain,
        string $operation,
        array  $context = [],
        float  $estimatedCost = 0.0
    ): array {
        // 1. Policy check (kill switch, feature flags, rollout)
        $policyResult = $this->policyEngine->evaluate($domain, $operation, array_merge($context, ['tenant_id' => $tenantId]));
        if (! $policyResult['allowed']) {
            return $this->deny('policy_violation', $policyResult['reason'], $policyResult['violations']);
        }

        // 2. Quota check
        $quotaResult = $this->quotaManager->check($tenantId);
        if (! $quotaResult['allowed']) {
            return $this->deny('quota_exceeded', "Daily quota of {$quotaResult['limit']} requests exhausted", []);
        }

        // 3. Rate limit check
        if (! $this->rateLimiter->allow($tenantId, $context['user_id'] ?? '')) {
            return $this->deny('rate_limited', 'Rate limit exceeded, please wait', []);
        }

        // 4. Budget check (if budget is configured)
        if ($estimatedCost > 0) {
            $budgetResult = $this->budgetManager->checkBudget($tenantId, $estimatedCost);
            if (! $budgetResult['allowed']) {
                return $this->deny('budget_exceeded', 'Daily AI budget exhausted', []);
            }
        }

        return [
            'allowed'    => true,
            'reason'     => 'authorized',
            'violations' => [],
            'quota'      => $quotaResult,
            'rate'       => $this->rateLimiter->getRemainingRequests($tenantId),
        ];
    }

    public function recordUsage(
        string $tenantId,
        string $provider,
        string $model,
        int    $inputTokens,
        int    $outputTokens
    ): void {
        $this->quotaManager->increment($tenantId);
        $this->quotaManager->incrementSystemTotal();

        $cost = $this->costEstimator->estimateCost($provider, $model, $inputTokens, $outputTokens);
        $this->costEstimator->recordUsage($tenantId, $provider, $model, $inputTokens, $outputTokens);

        if ($cost > 0) {
            $this->budgetManager->recordSpend($tenantId, $cost);
        }
    }

    public function getGovernanceDashboard(string $tenantId): array
    {
        return [
            'quota'  => $this->quotaManager->check($tenantId),
            'rate'   => $this->rateLimiter->getRemainingRequests($tenantId),
            'budget' => $this->budgetManager->getBudgetStatus($tenantId),
            'cost_7d'=> $this->costEstimator->getTenantTotalCost($tenantId, 7),
        ];
    }

    private function deny(string $type, string $reason, array $violations): array
    {
        return ['allowed' => false, 'type' => $type, 'reason' => $reason, 'violations' => $violations];
    }
}
