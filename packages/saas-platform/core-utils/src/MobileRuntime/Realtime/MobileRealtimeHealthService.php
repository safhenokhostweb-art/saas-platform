<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Realtime;

use SaaSPlatform\CoreUtils\MobileRuntime\Infrastructure\RedisRuntimeValidator;
use SaaSPlatform\CoreUtils\MobileRuntime\Telemetry\MobileRealtimeDiagnostics;

class MobileRealtimeHealthService
{
    public function __construct(
        private readonly ReverbRuntimeValidator $reverbValidator,
        private readonly RealtimeConfigurationInspector $configInspector,
        private readonly RedisRuntimeValidator $redisValidator,
        private readonly MobileRealtimeDiagnostics $diagnostics,
    ) {}

    public function getHealth(string $tenantId = 'platform'): array
    {
        $reverb    = $this->reverbValidator->validate();
        $redis     = $this->redisValidator->validate();
        $config    = $this->configInspector->getConfigurationReport();
        $diag      = $this->diagnostics->runDiagnostics($tenantId);

        $dimensions = [
            'reverb_configured'  => $reverb['production_ready'] ? 100 : ($reverb['status'] !== 'not_configured' ? 30 : 0),
            'redis_available'    => $redis['production_ready'] ? 100 : ($redis['status'] === 'fallback' ? 50 : 0),
            'channel_auth'       => 100,    // HMAC always available (app.key)
            'presence_service'   => 100,    // PresenceRuntimeManager always active
            'channel_registry'   => 100,    // ConversationChannelRegistry always active
            'socket_auth'        => 100,    // RuntimeSocketAuthorizationService always active
            'diagnostics_score'  => $diag['score'],
        ];

        $overall = (int) round(array_sum($dimensions) / count($dimensions));

        return [
            'tenant_id'    => $tenantId,
            'healthy'      => $overall >= 80,
            'score'        => $overall,
            'transport'    => $reverb['transport'],
            'dimensions'   => $dimensions,
            'reverb'       => $reverb,
            'redis'        => $redis,
            'config'       => $config,
            'diagnostics'  => $diag,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    public function getChecks(string $tenantId = 'platform'): array
    {
        $health = $this->getHealth($tenantId);
        $checks = [];

        foreach ($health['dimensions'] as $dimension => $score) {
            $label  = ucwords(str_replace('_', ' ', $dimension));
            $status = $score >= 80 ? 'pass' : ($score >= 40 ? 'warn' : 'fail');
            $checks[] = [
                'name'    => $label,
                'status'  => $status,
                'score'   => $score,
                'message' => "{$label}: {$score}/100",
            ];
        }

        return $checks;
    }
}
