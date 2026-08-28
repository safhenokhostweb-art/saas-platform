<?php

namespace Tests\Feature;

use Tests\TestCase;

class PaymentConnectionWarningDefaultsTest extends TestCase
{
    public function test_feature_ships_disabled_and_fail_open_with_no_env_vars_set(): void
    {
        $this->assertFalse(config('payment-connection-warning.enabled'));
        $this->assertFalse(config('payment-connection-warning.country_header_verified'));

        $this->postJson(route('payment-connection.preflight'), [], [
            'X-Country-Code' => 'DE',
            'X-Origin-Peer-IP' => '185.143.232.10',
        ])->assertOk()->assertJson([
            'allowed' => true,
            'requires_warning' => false,
            'status' => 'disabled',
        ]);
    }
}
