<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Product;
use App\Observers\ProductObserver;
use App\Services\Payment\PaymentGatewayInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $driver = config('payment.default', 'zarinpal');
            $class = config("payment.gateways.{$driver}");
            if (!$class || !class_exists($class)) {
                $class = \App\Services\Payment\ZarinpalGateway::class;
            }
            return $app->make($class);
        });
    }

    public function boot(): void
    {
        Product::observe(ProductObserver::class);
    }
}
