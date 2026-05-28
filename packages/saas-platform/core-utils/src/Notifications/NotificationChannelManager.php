<?php

namespace SaaSPlatform\CoreUtils\Notifications;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationChannelManager
{
    public function send(string $channel, array $payload): array
    {
        return match($channel) {
            'email'  => $this->sendEmail($payload),
            'sms'    => $this->sendSms($payload),
            'in_app' => $this->sendInApp($payload),
            default  => ['success' => false, 'reason' => "unknown_channel:{$channel}"],
        };
    }

    private function sendEmail(array $payload): array
    {
        try {
            // Queued mail dispatch — actual Mailable class resolved at runtime
            $email = $this->resolveRecipientEmail($payload['user_id'] ?? 0, $payload['tenant_id'] ?? '');
            if (! $email) {
                return ['success' => false, 'reason' => 'no_email_address'];
            }

            // Dispatch to queue (non-blocking)
            \Illuminate\Support\Facades\Queue::push(function () use ($payload, $email) {
                Mail::raw(
                    "{$payload['title']}\n\n{$payload['body']}",
                    fn ($m) => $m->to($email)->subject($payload['title'])
                );
            });

            return ['success' => true, 'channel' => 'email'];
        } catch (\Throwable $e) {
            Log::warning('Notification email failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'reason' => $e->getMessage()];
        }
    }

    private function sendSms(array $payload): array
    {
        try {
            $gateway = $this->resolveSmsGateway($payload['tenant_id'] ?? '');
            if (! $gateway) {
                return ['success' => false, 'reason' => 'no_sms_gateway'];
            }

            $phone = $this->resolveRecipientPhone($payload['user_id'] ?? 0, $payload['tenant_id'] ?? '');
            if (! $phone) {
                return ['success' => false, 'reason' => 'no_phone_number'];
            }

            $result = $gateway->send($phone, substr($payload['body'], 0, 160));
            return ['success' => (bool) $result, 'channel' => 'sms'];
        } catch (\Throwable $e) {
            Log::warning('Notification SMS failed', ['error' => $e->getMessage()]);
            return ['success' => false, 'reason' => $e->getMessage()];
        }
    }

    private function sendInApp(array $payload): array
    {
        // In-app is handled by UnifiedNotificationService::storeInApp()
        return ['success' => true, 'channel' => 'in_app'];
    }

    private function resolveRecipientEmail(int $userId, string $tenantId): ?string
    {
        try {
            $userModel = config('auth.providers.users.model', \App\Models\User::class);
            $user = $userModel::find($userId);
            return $user?->email;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveRecipientPhone(int $userId, string $tenantId): ?string
    {
        try {
            $userModel = config('auth.providers.users.model', \App\Models\User::class);
            $user = $userModel::find($userId);
            return $user?->phone ?? $user?->mobile ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveSmsGateway(string $tenantId): mixed
    {
        $resolver = config('tickets.sms_gateway_resolver');
        if ($resolver && is_callable($resolver)) {
            return $resolver($tenantId);
        }
        return null;
    }
}
