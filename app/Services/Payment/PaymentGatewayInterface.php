<?php
namespace App\Services\Payment;

interface PaymentGatewayInterface
{
    /**
     * نام درگاه برای ذخیره در فاکتور (مثلاً zarinpal، idpay).
     */
    public function name(): string;

    /**
     * درخواست پرداخت؛ برگرداندن آدرس ریدایرکت به درگاه.
     */
    public function request(int $amountRial, string $callbackUrl, string $description, ?string $orderId = null): array;

    /**
     * تأیید پرداخت بعد از callback؛ برگرداندن [ 'verified' => bool, 'ref' => ?string ].
     * برای زرین‌پال: authority = Authority، orderId = null
     * برای آی‌دی‌پی: authority = id، orderId = order_id
     */
    public function verify(string $authority, int $amountRial, ?string $orderId = null): array;
}
