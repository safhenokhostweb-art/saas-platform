<?php

namespace SaaSPlatform\CoreUtils\AI\Safety;

use Illuminate\Support\Facades\Cache;

class AiTenantBoundaryValidator
{
    public function validateRequest(string $tenantId, array $payload): array
    {
        // Ensure no cross-tenant references in the prompt
        $text = json_encode($payload);

        // Check for explicit tenant ID leakage from OTHER tenants
        // We accept the current tenant's ID but flag foreign ones
        if (preg_match_all('/(?:site_id|tenant_id)\s*[:=]\s*(\d+)/i', $text, $matches)) {
            foreach ($matches[1] as $referencedId) {
                if ($referencedId !== $tenantId && $referencedId !== '' && $tenantId !== '') {
                    return [
                        'valid'  => false,
                        'reason' => 'cross_tenant_reference_detected',
                        'detail' => "Request references foreign tenant: {$referencedId}",
                    ];
                }
            }
        }

        return ['valid' => true];
    }

    public function validateResponse(string $response, string $tenantId): array
    {
        // Check if response contains data that belongs to a different tenant
        if (preg_match_all('/(?:site_id|tenant_id)\s*[:=]\s*(\d+)/i', $response, $matches)) {
            foreach ($matches[1] as $referencedId) {
                if ($referencedId !== $tenantId && $referencedId !== '' && $tenantId !== '') {
                    $this->recordLeakAttempt($tenantId, $referencedId);
                    return [
                        'valid'  => false,
                        'reason' => 'cross_tenant_data_leak',
                        'detail' => "Response contains foreign tenant data: {$referencedId}",
                    ];
                }
            }
        }

        return ['valid' => true];
    }

    public function getLeakHistory(string $tenantId): array
    {
        return Cache::get("ai:boundary:leaks:{$tenantId}", []);
    }

    private function recordLeakAttempt(string $tenantId, string $foreignId): void
    {
        $key  = "ai:boundary:leaks:{$tenantId}";
        $logs = Cache::get($key, []);
        $logs[] = ['foreign_tenant' => $foreignId, 'at' => now()->toISOString()];
        Cache::put($key, array_slice($logs, -50), now()->addDays(30));
    }
}
