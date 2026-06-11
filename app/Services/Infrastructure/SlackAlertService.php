<?php
namespace App\Services\Infrastructure;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends structured alert messages to a Slack webhook.
 * Requires SLACK_ALERT_WEBHOOK env var.
 * Silently no-ops when webhook is not configured.
 *
 * Severity levels: critical | high | warning | info
 */
class SlackAlertService
{
    private ?string $webhook;
    private string  $channel;
    private string  $appName;

    public function __construct()
    {
        $this->webhook = config('services.slack_alert.webhook');
        $this->channel = config('services.slack_alert.channel', '#alerts');
        $this->appName = config('app.name', 'SaaS Platform');
    }

    public function critical(string $title, string $body, array $context = []): void
    {
        $this->send('critical', $title, $body, $context);
    }

    public function high(string $title, string $body, array $context = []): void
    {
        $this->send('high', $title, $body, $context);
    }

    public function warning(string $title, string $body, array $context = []): void
    {
        $this->send('warning', $title, $body, $context);
    }

    public function info(string $title, string $body, array $context = []): void
    {
        $this->send('info', $title, $body, $context);
    }

    public function deploymentEvent(string $event, string $details = ''): void
    {
        $this->send('info', "🚀 Deploy: {$event}", $details ?: 'No additional details.');
    }

    private function send(string $severity, string $title, string $body, array $context = []): void
    {
        if (empty($this->webhook)) {
            return;
        }

        $color = match ($severity) {
            'critical' => '#dc2626',
            'high'     => '#ea580c',
            'warning'  => '#d97706',
            default    => '#2563eb',
        };

        $emoji = match ($severity) {
            'critical' => '🔴',
            'high'     => '🟠',
            'warning'  => '🟡',
            default    => '🔵',
        };

        $fields = [];
        foreach ($context as $k => $v) {
            $fields[] = [
                'title' => (string) $k,
                'value' => (string) $v,
                'short' => true,
            ];
        }

        $fields[] = [
            'title' => 'Environment',
            'value' => app()->environment(),
            'short' => true,
        ];

        $fields[] = [
            'title' => 'Time',
            'value' => now()->format('Y-m-d H:i:s T'),
            'short' => true,
        ];

        $payload = [
            'channel'     => $this->channel,
            'username'    => $this->appName,
            'icon_emoji'  => ':bell:',
            'attachments' => [
                [
                    'color'      => $color,
                    'title'      => "{$emoji} [{$severity}] {$title}",
                    'text'       => $body,
                    'fields'     => $fields,
                    'footer'     => $this->appName,
                    'ts'         => now()->timestamp,
                    'mrkdwn_in'  => ['text'],
                ],
            ],
        ];

        try {
            Http::timeout(5)->post($this->webhook, $payload);
        } catch (\Throwable $e) {
            Log::warning('SlackAlertService: webhook delivery failed', ['error' => $e->getMessage()]);
        }
    }
}
