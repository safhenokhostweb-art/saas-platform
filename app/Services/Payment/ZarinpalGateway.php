<?php

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZarinpalGateway implements PaymentGatewayInterface
{
    protected string $merchantId;
    protected bool $sandbox;

    /**
     * @param array{merchant_id?: string, sandbox?: bool} $config اگر خالی باشد از config/services استفاده می‌شود
     */
    public function __construct(array $config = [])
    {
        $this->merchantId = $config['merchant_id'] ?? config('services.zarinpal.merchant_id', '');
        $this->sandbox = isset($config['sandbox']) ? (bool) $config['sandbox'] : config('services.zarinpal.sandbox', true);
    }

    public function name(): string
    {
        return 'zarinpal';
    }

    protected function baseUrl(): string
    {
        return $this->sandbox
            ? 'https://sandbox.zarinpal.com/pg/rest/WebGate'
            : 'https://api.zarinpal.com/pg/rest/WebGate';
    }

    /**
     * @return array{success: bool, authority?: string, redirect_url?: string, error?: string}
     */
    public function request(int $amountRial, string $callbackUrl, string $description, ?string $orderId = null): array
    {
        if (empty($this->merchantId)) {
            Log::warning('Zarinpal: merchant_id not set');
            return ['success' => false, 'error' => 'درگاه تنظیم نشده است.'];
        }

        $payload = [
            'MerchantID' => $this->merchantId,
            'Amount' => $amountRial,
            'CallbackURL' => $callbackUrl,
            'Description' => $description,
        ];

        try {
            $response = Http::timeout(15)
                ->asForm()
                ->post($this->baseUrl() . '/PaymentRequest.json', $payload);

            $data = $response->json();
            $status = (int) ($data['Status'] ?? -1);

            if ($status === 100 && !empty($data['Authority'])) {
                $startUrl = $this->sandbox
                    ? 'https://sandbox.zarinpal.com/pg/StartPay/'
                    : 'https://www.zarinpal.com/pg/StartPay/';
                return [
                    'success' => true,
                    'authority' => $data['Authority'],
                    'redirect_url' => $startUrl . $data['Authority'],
                ];
            }

            $errors = [
                -1 => 'اطلاعات ارسال شده ناقص است.',
                -2 => 'IP یا مرچنت کد پذیرنده صحیح نیست.',
                -3 => 'با توجه به محدودیت‌های شاپرک امکان پرداخت با رقم درخواست شده میسر نیست.',
                -4 => 'سطح تأیید پذیرنده پایین‌تر از سطح نقره‌ای است.',
                -11 => 'درخواست مورد نظر یافت نشد.',
                -12 => 'امکان ویرایش درخواست میسر نیست.',
                -21 => 'هیچ نوع عملیات مالی برای این تراکنش یافت نشد.',
                -22 => 'تراکنش ناموفق است.',
                -33 => 'رقم تراکنش با رقم پرداخت شده مطابقت ندارد.',
                -34 => 'سقف تقسیم تراکنش از لحاظ تعداد یا رقم عبور کرده است.',
                -40 => 'اجازه دسترسی به این API وجود ندارد.',
                -41 => 'اطلاعات ارسال شده مربوط به AdditionalData غیرمعتبر است.',
                -42 => 'مدت زمان معتبر طول عمر شناسه پرداخت باید بین ۳۰ دقیقه تا ۴۵ روز می‌باشد.',
                -54 => 'درخواست مورد نظر آرشیو شده است.',
                100 => 'عملیات با موفقیت انجام شد.',
                101 => 'عملیات پرداخت با موفقیت انجام شده است.',
            ];
            return [
                'success' => false,
                'error' => $errors[$status] ?? ('خطای درگاه: ' . $status),
            ];
        } catch (\Throwable $e) {
            Log::error('Zarinpal request exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'error' => 'خطا در ارتباط با درگاه پرداخت.'];
        }
    }

    /**
     * @return array{verified: bool, ref?: string, error?: string}
     */
    public function verify(string $authority, int $amountRial, ?string $orderId = null): array
    {
        if (empty($this->merchantId)) {
            return ['verified' => false, 'error' => 'درگاه تنظیم نشده است.'];
        }

        $payload = [
            'MerchantID' => $this->merchantId,
            'Authority' => $authority,
            'Amount' => $amountRial,
        ];

        try {
            $response = Http::timeout(15)
                ->asForm()
                ->post($this->baseUrl() . '/PaymentVerification.json', $payload);

            $data = $response->json();
            $status = (int) ($data['Status'] ?? -1);

            if ($status === 100 && !empty($data['RefID'])) {
                return [
                    'verified' => true,
                    'ref' => (string) $data['RefID'],
                ];
            }

            return [
                'verified' => false,
                'error' => 'تراکنش ناموفق یا قبلاً تأیید شده است.',
            ];
        } catch (\Throwable $e) {
            Log::error('Zarinpal verify exception', ['message' => $e->getMessage()]);
            return ['verified' => false, 'error' => 'خطا در تأیید پرداخت.'];
        }
    }
}
