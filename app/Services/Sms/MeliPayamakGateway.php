<?php
namespace App\Services\Sms;

use App\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * درایور ملی‌پیامک (REST API).
 * مستندات: https://www.melipayamak.com/api/
 * قالب پیامک: متن ساده؛ برای ارسال با الگو (pattern) از متد sendByPattern استفاده کنید.
 */
class MeliPayamakGateway implements SmsGatewayInterface
{
    protected string $url;
    protected string $username;
    protected string $password;
    protected string $from;

    public function __construct(array $config)
    {
        $this->url = $config['url'] ?? 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';
        $this->username = $config['username'] ?? '';
        $this->password = $config['password'] ?? '';
        $this->from = $config['from'] ?? '';
    }

    public function send(string $to, string $message): bool
    {
        $to = $this->normalizeMobile($to);
        if ($to === '' || $this->username === '' || $this->password === '') {
            return false;
        }

        // ملی‌پیامک REST: ارسال ساده (مستندات کنسول جدید با Token متفاوت است؛ این نمونه با Send.asmx ساده)
        try {
            $response = Http::asForm()->post($this->url, [
                'username' => $this->username,
                'password' => $this->password,
                'to' => $to,
                'from' => $this->from ?: $this->username,
                'text' => $message,
                'isflash' => false,
            ]);
            if ($response->successful()) {
                $body = $response->json();
                $value = $body['Value'] ?? $body['value'] ?? null;
                if ($value !== null && $value !== '' && (is_numeric($value) ? (int) $value > 0 : true)) {
                    return true;
                }
            }
            Log::channel('single')->warning('MeliPayamak send failed', ['response' => $response->body(), 'to' => $to]);
            return false;
        } catch (\Throwable $e) {
            Log::channel('single')->error('MeliPayamak send exception', ['message' => $e->getMessage(), 'to' => $to]);
            return false;
        }
    }

    protected function normalizeMobile(string $mobile): string
    {
        $mobile = preg_replace('/\D/', '', $mobile);
        if (str_starts_with($mobile, '98')) {
            return $mobile;
        }
        if (str_starts_with($mobile, '0')) {
            return '98' . substr($mobile, 1);
        }
        if (strlen($mobile) === 10) {
            return '98' . $mobile;
        }
        return $mobile;
    }
}
