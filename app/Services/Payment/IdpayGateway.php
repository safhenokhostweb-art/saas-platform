<?php
namespace App\Services\Payment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IdpayGateway implements PaymentGatewayInterface
{
    protected string $apiKey;
    protected bool $sandbox;

    /**
     * @param array{api_key?: string, sandbox?: bool} $config
     */
    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? config('services.idpay.api_key', '');
        $this->sandbox = isset($config['sandbox']) ? (bool) $config['sandbox'] : config('services.idpay.sandbox', true);
    }

    public function name(): string
    {
        return 'idpay';
    }

    protected function baseUrl(): string
    {
        return 'https://api.idpay.ir/v1.1';
    }

    /**
     * @return array{success: bool, authority?: string, redirect_url?: string, error?: string}
     */
    public function request(int $amountRial, string $callbackUrl, string $description, ?string $orderId = null): array
    {
        if (empty($this->apiKey)) {
            Log::warning('IDPay: api_key not set');
            return ['success' => false, 'error' => 'درگاه تنظیم نشده است.'];
        }

        $orderId = $orderId ?: (string) uniqid('ord_', true);
        if (strlen($orderId) < 10) {
            $orderId = str_pad($orderId, 10, '0', STR_PAD_LEFT);
        }

        $payload = [
            'order_id' => $orderId,
            'amount' => $amountRial,
            'name' => '',
            'phone' => '',
            'mail' => '',
            'desc' => $description,
            'callback' => $callbackUrl,
        ];

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-API-KEY' => $this->apiKey,
                    'X-SANDBOX' => $this->sandbox ? '1' : '0',
                ])
                ->post($this->baseUrl() . '/payment', $payload);

            $data = $response->json();

            if (!empty($data['id']) && !empty($data['link'])) {
                return [
                    'success' => true,
                    'authority' => $data['id'],
                    'redirect_url' => $data['link'],
                ];
            }

            $errorMsg = $data['error_message'] ?? $data['error_code'] ?? 'خطای درگاه';
            return ['success' => false, 'error' => $errorMsg];
        } catch (\Throwable $e) {
            Log::error('IDPay request exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'error' => 'خطا در ارتباط با درگاه پرداخت.'];
        }
    }

    /**
     * @return array{verified: bool, ref?: string, error?: string}
     */
    public function verify(string $authority, int $amountRial, ?string $orderId = null): array
    {
        if (empty($this->apiKey)) {
            return ['verified' => false, 'error' => 'درگاه تنظیم نشده است.'];
        }

        $payload = [
            'id' => $authority,
            'order_id' => $orderId ?? '',
        ];

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-API-KEY' => $this->apiKey,
                    'X-SANDBOX' => $this->sandbox ? '1' : '0',
                ])
                ->post($this->baseUrl() . '/payment/verify', $payload);

            $data = $response->json();

            if (isset($data['status']) && (int) $data['status'] === 100 && !empty($data['track_id'])) {
                return [
                    'verified' => true,
                    'ref' => (string) $data['track_id'],
                ];
            }

            return [
                'verified' => false,
                'error' => $data['error_message'] ?? 'تراکنش ناموفق یا قبلاً تأیید شده است.',
            ];
        } catch (\Throwable $e) {
            Log::error('IDPay verify exception', ['message' => $e->getMessage()]);
            return ['verified' => false, 'error' => 'خطا در تأیید پرداخت.'];
        }
    }
}
