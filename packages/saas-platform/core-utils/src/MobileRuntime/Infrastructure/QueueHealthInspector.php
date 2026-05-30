<?php

namespace SaaSPlatform\CoreUtils\MobileRuntime\Infrastructure;

use Illuminate\Support\Facades\Config;

class QueueHealthInspector
{
    private const PRODUCTION_DRIVERS = ['redis', 'beanstalkd', 'sqs', 'horizon'];
    private const ACCEPTABLE_DRIVERS = ['database'];
    private const DEV_ONLY_DRIVERS   = ['sync', 'null'];

    public function validate(): array
    {
        $driver     = Config::get('queue.default', 'sync');
        $isProd     = in_array($driver, self::PRODUCTION_DRIVERS, true);
        $isOk       = $isProd || in_array($driver, self::ACCEPTABLE_DRIVERS, true);
        $isDevOnly  = in_array($driver, self::DEV_ONLY_DRIVERS, true);

        return [
            'driver'           => $driver,
            'production_ready' => $isProd,
            'acceptable'       => $isOk,
            'dev_only'         => $isDevOnly,
            'message'          => $this->driverMessage($driver, $isProd, $isOk),
            'connections'      => array_keys(Config::get('queue.connections', [])),
        ];
    }

    public function getHealthCheck(): array
    {
        $result = $this->validate();
        $status = $result['production_ready'] ? 'pass' : ($result['acceptable'] ? 'warn' : 'warn');
        return [
            'name'    => 'Queue Runtime',
            'status'  => $status,
            'message' => $result['message'],
            'details' => $result,
        ];
    }

    public function isProductionReady(): bool
    {
        return $this->validate()['production_ready'];
    }

    private function driverMessage(string $driver, bool $isProd, bool $isOk): string
    {
        if ($isProd) {
            return "Queue driver '{$driver}' is production-ready";
        }
        if ($isOk) {
            return "Queue driver '{$driver}' is acceptable but not optimal for production";
        }
        return "Queue driver '{$driver}' is for development only — switch to redis for production";
    }
}
