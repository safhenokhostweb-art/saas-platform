<?php

namespace Tests\Unit\CustomerInstallation\Ssl;

use App\Models\Infrastructure\DnsProviderSetting;
use App\Services\CustomerInstallation\CredentialVault;
use App\Services\CustomerInstallation\Ssl\ArvanCdnDomainDecider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 15-B1 — see docs/ARVAN_SSL_FINAL_AUDIT.md: ArvanCloud terminates a
 * real Let's Encrypt certificate at its own edge for any domain it proxies
 * (cloud_proxy=true). These tests confirm the decider's three guard
 * conditions (active, ready, cloud_proxy) and the subdomain-of-root check.
 */
class ArvanCdnDomainDeciderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $key = base64_encode(random_bytes(32));
        putenv("INSTALLER_MASTER_KEY={$key}");
        $_ENV['INSTALLER_MASTER_KEY'] = $key;
    }

    private function activeSetting(bool $cloudProxy): DnsProviderSetting
    {
        return DnsProviderSetting::create([
            'provider'            => 'arvan',
            'root_domain'         => 'safhenokhost.ir',
            'is_active'           => true,
            'cloud_proxy'         => $cloudProxy,
            'api_token_encrypted' => (new CredentialVault())->encrypt('token'),
        ]);
    }

    public function test_returns_false_when_no_dns_provider_setting_exists(): void
    {
        $this->assertFalse((new ArvanCdnDomainDecider())->isManagedByArvan('tenant1.safhenokhost.ir'));
    }

    public function test_returns_false_when_cloud_proxy_is_disabled(): void
    {
        $this->activeSetting(cloudProxy: false);

        $this->assertFalse((new ArvanCdnDomainDecider())->isManagedByArvan('tenant1.safhenokhost.ir'));
    }

    public function test_returns_false_when_setting_is_not_active(): void
    {
        DnsProviderSetting::create([
            'provider' => 'arvan', 'root_domain' => 'safhenokhost.ir',
            'is_active' => false, 'cloud_proxy' => true,
            'api_token_encrypted' => (new CredentialVault())->encrypt('token'),
        ]);

        $this->assertFalse((new ArvanCdnDomainDecider())->isManagedByArvan('tenant1.safhenokhost.ir'));
    }

    public function test_returns_true_for_a_subdomain_of_root_when_cloud_proxy_is_enabled(): void
    {
        $this->activeSetting(cloudProxy: true);

        $this->assertTrue((new ArvanCdnDomainDecider())->isManagedByArvan('tenant1.safhenokhost.ir'));
    }

    public function test_returns_false_for_the_apex_root_domain_itself(): void
    {
        $this->activeSetting(cloudProxy: true);

        $this->assertFalse((new ArvanCdnDomainDecider())->isManagedByArvan('safhenokhost.ir'));
    }

    public function test_returns_false_for_a_customers_own_custom_domain_even_with_cloud_proxy_enabled(): void
    {
        $this->activeSetting(cloudProxy: true);

        $this->assertFalse((new ArvanCdnDomainDecider())->isManagedByArvan('example.com'));
    }

    public function test_returns_false_when_api_token_is_missing_even_if_active_and_cloud_proxy_true(): void
    {
        DnsProviderSetting::create([
            'provider' => 'arvan', 'root_domain' => 'safhenokhost.ir',
            'is_active' => true, 'cloud_proxy' => true,
            'api_token_encrypted' => null,
        ]);

        $this->assertFalse((new ArvanCdnDomainDecider())->isManagedByArvan('tenant1.safhenokhost.ir'));
    }
}
