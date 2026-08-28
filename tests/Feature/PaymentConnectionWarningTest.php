<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use SaaSPlatform\CoreUtils\Contracts\PaymentConnectionSettingsResolverContract;
use Tests\TestCase;

class PaymentConnectionWarningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment-connection-warning.enabled' => true,
            'payment-connection-warning.country_header_verified' => true,
            'payment-connection-warning.trusted_proxy_cidr_file' => '',
            'payment-connection-warning.trusted_proxy_cidrs' => ['185.143.232.0/22', '2a0c:a7c0::/29'],
            'payment-connection-warning.exempt_countries' => ['IR'],
            'payment-connection-warning.warning_countries' => ['*'],
        ]);
    }

    public function test_ir_from_trusted_arvan_peer_allows_direct_payment(): void
    {
        $this->postJson(route('payment-connection.preflight'), [], $this->edgeHeaders('IR'))
            ->assertOk()
            ->assertJson(['allowed' => true, 'requires_warning' => false, 'status' => 'domestic']);
    }

    public function test_foreign_country_from_trusted_arvan_peer_requires_warning(): void
    {
        $this->postJson(route('payment-connection.preflight'), [], $this->edgeHeaders('DE'))
            ->assertOk()
            ->assertJson(['allowed' => false, 'requires_warning' => true, 'status' => 'foreign_warning']);
    }

    public function test_spoofed_ir_from_untrusted_peer_is_not_trusted_and_fails_open(): void
    {
        Log::spy();

        $this->postJson(route('payment-connection.preflight'), [], $this->edgeHeaders('IR', '203.0.113.44'))
            ->assertOk()
            ->assertJson(['allowed' => true, 'requires_warning' => false, 'status' => 'unknown_fail_open']);

        Log::shouldHaveReceived('notice')->once()->with(
            'payment_connection.country_unavailable',
            ['reason' => 'origin_peer_untrusted'],
        );
    }

    public function test_missing_or_invalid_country_is_fail_open(): void
    {
        foreach ([null, '', 'IRAN', '1R'] as $country) {
            $headers = ['X-Origin-Peer-IP' => '185.143.232.10'];
            if ($country !== null) {
                $headers['X-Country-Code'] = $country;
            }

            $this->postJson(route('payment-connection.preflight'), [], $headers)
                ->assertOk()
                ->assertJson(['allowed' => true, 'requires_warning' => false, 'status' => 'unknown_fail_open']);
        }
    }

    public function test_recheck_after_connection_changes_to_iran_allows_payment(): void
    {
        $this->postJson(route('payment-connection.preflight'), [], $this->edgeHeaders('TR'))
            ->assertJson(['allowed' => false]);

        $this->postJson(route('payment-connection.preflight'), [], $this->edgeHeaders('IR'))
            ->assertJson(['allowed' => true, 'status' => 'domestic']);
    }

    public function test_explicit_continue_allows_foreign_connection(): void
    {
        $this->postJson(route('payment-connection.preflight'), ['override' => true], $this->edgeHeaders('NL'))
            ->assertOk()
            ->assertJson(['allowed' => true, 'requires_warning' => false, 'status' => 'foreign_overridden']);
    }

    public function test_local_development_without_cdn_is_fail_open(): void
    {
        $this->postJson(route('payment-connection.preflight'))
            ->assertOk()
            ->assertJson(['allowed' => true, 'status' => 'unknown_fail_open']);
    }

    public function test_country_header_is_fail_open_until_arvan_overwrite_has_been_verified(): void
    {
        config(['payment-connection-warning.country_header_verified' => false]);

        $this->postJson(route('payment-connection.preflight'), [], $this->edgeHeaders('DE'))
            ->assertOk()
            ->assertJson(['allowed' => true, 'status' => 'unknown_fail_open']);
    }

    public function test_controlled_settings_resolver_can_override_exemptions_per_tenant(): void
    {
        $this->app->instance(PaymentConnectionSettingsResolverContract::class, new class implements PaymentConnectionSettingsResolverContract {
            public function resolve(\Illuminate\Http\Request $request): array
            {
                return [
                    'enabled' => true,
                    'warning_countries' => ['*'],
                    'exempt_countries' => $request->getHost() === 'tenant-a.test' ? ['IR', 'DE'] : ['IR'],
                ];
            }
        });

        $this->postJson('http://tenant-a.test/payment-connection/preflight', [], $this->edgeHeaders('DE'))
            ->assertJson(['allowed' => true, 'status' => 'domestic']);
        $this->postJson('http://tenant-b.test/payment-connection/preflight', [], $this->edgeHeaders('DE'))
            ->assertJson(['allowed' => false, 'status' => 'foreign_warning']);
    }

    public function test_shared_component_has_rtl_accessible_dialog_and_duplicate_submit_guard(): void
    {
        $html = Blade::render('<x-core-ui::payment-connection-warning />');

        $this->assertStringContainsString('<dialog', $html);
        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('aria-labelledby=', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
        $this->assertStringContainsString('if (busy) return', $html);
        $this->assertStringContainsString('requestSubmit', $html);
        $this->assertStringContainsString('فیلترشکن را خاموش کردم', $html);
        $this->assertStringContainsString('ادامه به پرداخت', $html);
    }

    /** @return array<string, string> */
    private function edgeHeaders(string $country, string $peer = '185.143.232.10'): array
    {
        return ['X-Country-Code' => $country, 'X-Origin-Peer-IP' => $peer];
    }
}
