<?php

namespace App\Services\Payment;

use App\Models\PaymentGateway;
use App\Models\Site;
use Illuminate\Support\Facades\Schema;

class PaymentGatewayResolver
{
    public function __construct(
        protected PaymentGatewayFactory $factory
    ) {}

    /**
     * درگاه مؤثر برای یک سایت یا پلتفرم.
     * اگر سایت درگاه داشته باشد از آن استفاده می‌شود، وگرنه درگاه پیش‌فرض پلتفرم.
     */
    public function resolveForSite(?int $siteId): PaymentGatewayInterface
    {
        if (Schema::hasTable('payment_gateways') && $siteId) {
            $site = Site::find($siteId);
            if ($site && $site->payment_gateway_id) {
                $gateway = $this->factory->fromId($site->payment_gateway_id);
                if ($gateway) {
                    return $gateway;
                }
            }
        }

        if (Schema::hasTable('payment_gateways')) {
            $default = PaymentGateway::getDefault();
            if ($default) {
                return $this->factory->fromModel($default);
            }
        }

        return app(PaymentGatewayInterface::class);
    }

    /**
     * شناسهٔ درگاه برای ذخیره در فاکتور (برای callback).
     */
    public function resolveGatewayIdForSite(?int $siteId): ?int
    {
        if (!Schema::hasTable('payment_gateways')) {
            return null;
        }
        if ($siteId) {
            $site = Site::find($siteId);
            if ($site && $site->payment_gateway_id) {
                return $site->payment_gateway_id;
            }
        }

        $default = PaymentGateway::getDefault();
        return $default?->id;
    }
}
