# افزودن درگاه پرداخت جدید

برای افزودن درگاه پرداخت جدید (مثلاً پی‌آی‌آر، ملت، سامان):

## ۱. ایجاد کلاس درگاه

کلاس جدید باید `PaymentGatewayInterface` را پیاده‌سازی کند:

```php
// app/Services/Payment/NewGateway.php
namespace App\Services\Payment;

class NewGateway implements PaymentGatewayInterface
{
    protected string $apiKey;
    protected bool $sandbox;

    public function __construct(array $config = [])
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->sandbox = $config['sandbox'] ?? true;
    }

    public function name(): string
    {
        return 'new_gateway';
    }

    public function request(int $amountRial, string $callbackUrl, string $description, ?string $orderId = null): array
    {
        // درخواست به API، برگرداندن:
        // ['success' => true, 'authority' => '...', 'redirect_url' => '...']
        // یا ['success' => false, 'error' => '...']
    }

    public function verify(string $authority, int $amountRial, ?string $orderId = null): array
    {
        // تأیید تراکنش، برگرداندن:
        // ['verified' => true, 'ref' => '...']
        // یا ['verified' => false, 'error' => '...']
    }
}
```

## ۲. ثبت در مدل و فکتوری

**`app/Models/PaymentGateway.php`:**

```php
public const DRIVER_NEW_GATEWAY = 'new_gateway';

public static function drivers(): array
{
    return [
        // ...
        self::DRIVER_NEW_GATEWAY => 'نام نمایشی',
    ];
}

public static function configFields(string $driver): array
{
    return match ($driver) {
        // ...
        self::DRIVER_NEW_GATEWAY => [
            'api_key' => ['label' => 'API Key', 'type' => 'text', 'required' => true],
            'sandbox' => ['label' => 'حالت تست', 'type' => 'boolean', 'required' => false],
        ],
        default => [],
    };
}
```

**`app/Services/Payment/PaymentGatewayFactory.php`:**

```php
protected array $driverMap = [
    // ...
    PaymentGatewayModel::DRIVER_NEW_GATEWAY => NewGateway::class,
];
```

## ۳. به‌روزرسانی کنترلر و فرم

**`app/Http/Controllers/Admin/PaymentGatewayController.php`:**

- در `addConfigValidationRules()` فیلدهای جدید را اضافه کنید.
- در `buildConfig()` مقادیر config را از request بخوانید.

**`resources/views/admin/payment-gateways/_form.blade.php`:**

- بلوک جدید برای فیلدهای درگاه با `data-driver="new_gateway"`.

## ۴. callback در CheckoutController

اگر درگاه پارامترهای callback متفاوتی دارد، در `callback()` متد `CheckoutController` شرط جدید اضافه کنید (مشابه idpay).

## ۵. تنظیم .env (fallback)

```
NEW_GATEWAY_API_KEY=...
NEW_GATEWAY_SANDBOX=true
```

و در `config/services.php` ثبت کنید.

---

**نکته:** تنظیمات حساس (api_key، merchant_id) به‌صورت رمزنگاری‌شده در دیتابیس ذخیره می‌شوند.
