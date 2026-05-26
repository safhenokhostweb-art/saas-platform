<?php

namespace SaaSPlatform\Modules\Tickets\Services;

use Illuminate\Support\Facades\Log;

class TicketSmsService
{
    private mixed $gateway = null;

    public function __construct()
    {
        try {
            $resolverClass = config('tickets.sms_gateway_resolver');
            if ($resolverClass && class_exists($resolverClass)) {
                $this->gateway = $resolverClass::resolve();
            }
        } catch (\Throwable $e) {
            $this->gateway = null;
        }
    }

    public function isEnabled(): bool
    {
        return (bool) $this->setting('tickets.sms_enabled', false);
    }

    public function getAdminPhone(): ?string
    {
        return $this->setting('tickets.sms_admin_phone') ?: null;
    }

    public function send(string $phone, string $message): bool
    {
        if (!$this->gateway) {
            Log::warning('tickets.sms: no gateway available');
            return false;
        }

        $phone = $this->normalizePhone($phone);
        if (!$phone) {
            Log::warning('tickets.sms: invalid phone', compact('phone'));
            return false;
        }

        try {
            return $this->gateway->send($phone, $message);
        } catch (\Throwable $e) {
            Log::error('tickets.sms.send_failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function renderTemplate(string $settingKey, array $vars, string $fallback = ''): string
    {
        $template = $this->setting($settingKey, $fallback);
        if (!$template) return $fallback;

        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }

        return $template;
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        $model = config('tickets.settings_model');
        return ($model && class_exists($model)) ? $model::get($key, $default) : $default;
    }

    private function normalizePhone(string $phone): ?string
    {
        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) === 10 && str_starts_with($phone, '9')) {
            return '0' . $phone;
        }
        if (strlen($phone) === 11 && str_starts_with($phone, '09')) {
            return $phone;
        }
        if (strlen($phone) === 12 && str_starts_with($phone, '989')) {
            return '0' . substr($phone, 2);
        }
        return strlen($phone) >= 10 ? $phone : null;
    }
}
