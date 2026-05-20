<?php
namespace App\Services\Sms;

use App\Contracts\SmsGatewayInterface;
use Illuminate\Support\Facades\App;

class SmsGatewayResolver
{
    public static function resolve(): ?SmsGatewayInterface
    {
        $provider = config('sms.default', 'meli_payamak');
        $credentials = config('sms.credentials.' . $provider, []);

        if ($provider === 'meli_payamak') {
            return new MeliPayamakGateway($credentials);
        }
        return App::bound('sms.gateway') ? App::make('sms.gateway') : new MeliPayamakGateway($credentials);
    }
}
