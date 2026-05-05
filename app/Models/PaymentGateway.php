<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentGateway extends Model
{
    protected $fillable = [
        'name',
        'driver',
        'config',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected $casts = [
        'config' => \App\Casts\EncryptedConfigCast::class,
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    public const DRIVER_ZARINPAL = 'zarinpal';
    public const DRIVER_IDPAY = 'idpay';

    public static function drivers(): array
    {
        return [
            self::DRIVER_ZARINPAL => 'زرین‌پال',
            self::DRIVER_IDPAY => 'آی‌دی‌پی',
        ];
    }

    public static function configFields(string $driver): array
    {
        return match ($driver) {
            self::DRIVER_ZARINPAL => [
                'merchant_id' => ['label' => 'مرچنت کد', 'type' => 'text', 'required' => true],
                'sandbox' => ['label' => 'حالت تست (سندباکس)', 'type' => 'boolean', 'required' => false],
            ],
            self::DRIVER_IDPAY => [
                'api_key' => ['label' => 'API Key', 'type' => 'text', 'required' => true],
                'sandbox' => ['label' => 'حالت تست (سندباکس)', 'type' => 'boolean', 'required' => false],
            ],
            default => [],
        };
    }

    public function sites()
    {
        return $this->hasMany(Site::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public static function getDefault(): ?self
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('payment_gateways')) {
            return null;
        }
        return static::where('is_active', true)->where('is_default', true)->first()
            ?? static::where('is_active', true)->orderBy('sort_order')->first();
    }
}
