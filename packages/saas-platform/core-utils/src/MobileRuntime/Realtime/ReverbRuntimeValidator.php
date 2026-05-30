<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Realtime;

use Illuminate\Support\Facades\Config;

class ReverbRuntimeValidator
{
    public const STATUS_NOT_CONFIGURED      = 'not_configured';
    public const STATUS_PARTIALLY_CONFIGURED = 'partially_configured';
    public const STATUS_CONFIGURED           = 'configured';
    public const STATUS_HEALTHY              = 'healthy';

    public function validate(): array
    {
        $cfg = $this->readConfig();
        [$status, $missing, $score] = $this->evaluate($cfg);

        return [
            'status'           => $status,
            'score'            => $score,
            'production_ready' => $status === self::STATUS_CONFIGURED || $status === self::STATUS_HEALTHY,
            'config'           => $cfg,
            'missing'          => $missing,
            'transport'        => $status !== self::STATUS_NOT_CONFIGURED ? 'websocket' : 'polling',
            'message'          => $this->statusMessage($status, $missing),
        ];
    }

    public function getStatus(): string
    {
        return $this->validate()['status'];
    }

    public function isConfigured(): bool
    {
        $status = $this->getStatus();
        return in_array($status, [self::STATUS_CONFIGURED, self::STATUS_HEALTHY], true);
    }

    public function getHealthCheck(): array
    {
        $result = $this->validate();
        return [
            'name'    => 'Reverb WebSocket',
            'status'  => $result['production_ready'] ? 'pass' : ($result['status'] === self::STATUS_NOT_CONFIGURED ? 'warn' : 'warn'),
            'message' => $result['message'],
            'details' => $result,
        ];
    }

    private function readConfig(): array
    {
        return [
            'app_key'    => Config::get('broadcasting.connections.reverb.key', ''),
            'app_secret' => Config::get('broadcasting.connections.reverb.secret', ''),
            'app_id'     => Config::get('broadcasting.connections.reverb.app_id', ''),
            'host'       => Config::get('broadcasting.connections.reverb.host', ''),
            'port'       => Config::get('broadcasting.connections.reverb.port', ''),
            'scheme'     => Config::get('broadcasting.connections.reverb.scheme', 'http'),
        ];
    }

    private function evaluate(array $cfg): array
    {
        $required = ['app_key', 'app_secret', 'app_id', 'host', 'port'];
        $missing  = array_filter($required, fn ($k) => empty($cfg[$k]));
        $filled   = count($required) - count($missing);
        $score    = (int) round(($filled / count($required)) * 100);

        if ($score === 0) {
            return [self::STATUS_NOT_CONFIGURED, array_values($missing), 0];
        }
        if ($score < 100) {
            return [self::STATUS_PARTIALLY_CONFIGURED, array_values($missing), $score];
        }
        return [self::STATUS_CONFIGURED, [], 100];
    }

    private function statusMessage(string $status, array $missing): string
    {
        return match ($status) {
            self::STATUS_NOT_CONFIGURED       => 'Reverb not configured — mobile realtime will use polling fallback',
            self::STATUS_PARTIALLY_CONFIGURED => 'Reverb partially configured — missing: ' . implode(', ', $missing),
            self::STATUS_CONFIGURED           => 'Reverb fully configured — WebSocket transport available',
            self::STATUS_HEALTHY              => 'Reverb healthy — WebSocket connections verified',
            default                           => 'Reverb configuration unknown',
        };
    }
}
