<?php
namespace App\Contracts;

/**
 * رابط ارسال پیامک — سازگار با سرویس‌های ایرانی (ملی‌پیامک، کاوه‌نگار، قاصدک و ...).
 */
interface SmsGatewayInterface
{
    /**
     * ارسال یک پیامک به یک شماره.
     *
     * @param string $to شماره گیرنده (مثلاً 09123456789)
     * @param string $message متن پیام (ترجیحاً کوتاه و کاربرپسند، رعایت محدودیت ۷۰ کاراکتر برای یک بخش فارسی)
     * @return bool موفق بودن ارسال
     */
    public function send(string $to, string $message): bool;
}
