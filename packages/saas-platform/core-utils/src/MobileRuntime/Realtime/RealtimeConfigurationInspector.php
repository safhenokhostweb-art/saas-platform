<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Realtime;

use Illuminate\Support\Facades\Config;

class RealtimeConfigurationInspector
{
    public function __construct(
        private readonly ReverbRuntimeValidator $reverbValidator,
    ) {}

    public function inspect(): array
    {
        $reverb      = $this->reverbValidator->validate();
        $broadcaster = Config::get('broadcasting.default', 'log');
        $reverbSet   = $broadcaster === 'reverb';

        return [
            'broadcaster'         => $broadcaster,
            'reverb_as_default'   => $reverbSet,
            'reverb'              => $reverb,
            'transport'           => $reverb['transport'],
            'mobile_channel_auth' => $this->checkChannelAuth(),
            'presence_channels'   => $this->checkPresenceChannels(),
            'readiness'           => $this->computeReadiness($reverb, $reverbSet),
        ];
    }

    public function getConfigurationReport(): array
    {
        $inspection = $this->inspect();
        return [
            'fully_configured' => $inspection['readiness']['score'] === 100,
            'score'            => $inspection['readiness']['score'],
            'issues'           => $inspection['readiness']['issues'],
            'transport'        => $inspection['transport'],
            'generated_at'     => now()->toIso8601String(),
        ];
    }

    private function checkChannelAuth(): array
    {
        $appKey = Config::get('app.key', '');
        return [
            'hmac_key_available' => ! empty($appKey),
            'status'             => ! empty($appKey) ? 'configured' : 'missing_app_key',
        ];
    }

    private function checkPresenceChannels(): array
    {
        return [
            'tenant_channel'       => 'presence-tenant.{tenantId}',
            'user_channel'         => 'private-mobile.user.{userId}',
            'conversation_channel' => 'presence-conversation.{conversationId}',
            'status'               => 'defined',
        ];
    }

    private function computeReadiness(array $reverb, bool $reverbSet): array
    {
        $issues = [];
        $score  = 0;

        if ($reverb['production_ready']) {
            $score += 60;
        } else {
            $issues[] = $reverb['message'];
        }

        if ($reverbSet) {
            $score += 20;
        } else {
            $issues[] = "Default broadcaster is '{$this->getBroadcaster()}' — set BROADCAST_CONNECTION=reverb";
        }

        if ($reverb['config']['scheme'] === 'https' || $reverb['config']['scheme'] === 'wss') {
            $score += 20;
        } else {
            $issues[] = 'Reverb using HTTP (not HTTPS/WSS) — production requires TLS';
        }

        return ['score' => $score, 'issues' => $issues];
    }

    private function getBroadcaster(): string
    {
        return Config::get('broadcasting.default', 'log');
    }
}
