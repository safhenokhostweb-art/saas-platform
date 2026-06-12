<?php
namespace App\Services\Payment;

use App\Models\PaymentGateway as PaymentGatewayModel;

class PaymentGatewayFactory
{
    protected array $driverMap = [
        PaymentGatewayModel::DRIVER_ZARINPAL => ZarinpalGateway::class,
        PaymentGatewayModel::DRIVER_IDPAY => IdpayGateway::class,
    ];

    public function fromModel(PaymentGatewayModel $model): PaymentGatewayInterface
    {
        $class = $this->driverMap[$model->driver] ?? null;
        if (!$class || !class_exists($class)) {
            throw new \InvalidArgumentException("درگاه {$model->driver} پشتیبانی نمی‌شود.");
        }

        $config = $model->config ?? [];

        return new $class($config);
    }

    public function fromId(?int $id): ?PaymentGatewayInterface
    {
        if (!$id) {
            return null;
        }
        $model = PaymentGatewayModel::find($id);
        return $model ? $this->fromModel($model) : null;
    }

    public function fromName(string $name): ?PaymentGatewayInterface
    {
        $model = PaymentGatewayModel::where('driver', $name)->where('is_active', true)->first();
        return $model ? $this->fromModel($model) : null;
    }
}
