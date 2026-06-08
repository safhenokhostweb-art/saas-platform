<?php
namespace App\Services\Sms;

use App\Contracts\SmsGatewayInterface;

/**
 * Resolves the active SMS gateway and applies automatic failover.
 *
 * Primary provider is driven by SMS_PROVIDER env.
 * Failover provider is driven by SMS_FAILOVER_PROVIDER env.
 * When the primary send() returns false, the failover provider is tried automatically.
 */
class SmsGatewayResolver
{
    public static function resolve(): SmsGatewayInterface
    {
        $primary  = config('sms.default', 'meli_payamak');
        $failover = config('sms.failover', null);

        $primaryGateway = self::make($primary);

        if ($failover && $failover !== $primary) {
            return new FailoverSmsGateway($primaryGateway, self::make($failover));
        }

        return $primaryGateway;
    }

    public static function make(string $provider): SmsGatewayInterface
    {
        $credentials = config("sms.credentials.{$provider}", []);

        return match ($provider) {
            'kavenegar'    => new KavenegarGateway($credentials),
            'ghasedak'     => new GhasedakGateway($credentials),
            default        => new MeliPayamakGateway($credentials),
        };
    }
}
